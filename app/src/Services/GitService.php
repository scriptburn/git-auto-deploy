<?php

namespace App\Services;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class GitService
{

    protected $git, $logger, $hookData, $request, $gitPath, $setting, $auth, $db, $utils, $log = [], $startedon, $binpaths;
    public $project;

    abstract public function parseRemoteRepoName();
    abstract public function parseRemoteBranchName();
    abstract public function parseRemoteUrl();
    abstract public function repoType();
    abstract public function identify($data);

    public function __construct1($git, $setting, $auth, $logger, $db, $utils)
    {

    }
    public function init($git, $setting, $auth, $logger, $db, $utils, $request, $binpaths)
    {
        $this->git     = $git;
        $this->setting = $setting;
        $this->auth    = $auth;
        $this->db      = $db;
        $this->utils   = $utils;

        $this->logger = $logger;

        $this->request  = $request;
        $this->binpaths = $binpaths;

        $this->parsePayLoad();
        $this->parseRemoteRepoName();
        $this->parseRemoteBranchName();

        $this->project = $this->git->findProject($this->getHookData('remote', 'repo_name'), $this->repoType());

        $index = array_search($this->getHookData('remote', 'branch_name'), array_column($this->project, 'branch'));

        if ($index === false)
        {
            throw new \Exception("Unknown branch '" . $this->getHookData('remote', 'branch_name') . "'");
        }
        else
        {
            $this->project = $this->project[$index];
        }

        $this->project['path'] = rtrim($this->project['path'], "/");

        if (!$this->project['status'])
        {
            throw new \Exception("Project '{$this->project['name']}' status is inactive");
        }
        elseif (empty($this->project['path']))
        {
            throw new \Exception("Local path not set for repo '{$this->project['name']}'  ");

        }

        $this->parseRemoteUrl();
        $this->startedon = microtime(true);
        $fields          = ['last_hook_time' => date("Y-m-d H:i:s", time())];
        $this->db->update($fields)
            ->table('projects')
            ->set($fields)
            ->where('id', '=', $this->project['id'])
            ->execute(false);

    }
    public function getHookData($index = "", $key = "")
    {
        if ($index && $key)
        {
            return isset($this->hookData[$index][$key]) ? $this->hookData[$index][$key] : null;
        }
        elseif ($index)
        {
            return isset($this->hookData[$index]) ? $this->hookData[$index] : null;
        }
        else
        {
            return $this->hookData;
        }
    }
    private function parsePayLoad()
    {
        if ($this->hookData['headers']['CONTENT_TYPE'][0] == 'application/x-www-form-urlencoded')
        {
            $this->hookData['post'] = (array) @json_decode($this->hookData['post']['payload'], true);

        }

    }
    public function sync()
    {

        $completed = false;
        try
        {
            if ($this->project['pre_hook'])
            {
                $data['hook_data'] = $this->hookData;
                $data['project']   = $this->project;
                $data['hook']      = 'pre';
                $data['status']    = 0;
                $this->log("Runing pre hook command:{$this->project['pre_hook']} ");

                $out = $this->exec(['cmd' => $this->project['pre_hook'], 'input' => json_encode($data)]);
                if (!$out[0])
                {
                    $this->log("Failed to run pre hook script");
                }

            }
            $repoPath = $this->project['path'] . "/.git/";
            if (!is_dir($repoPath) || !is_file($repoPath . 'HEAD'))
            {

                $this->log("Absent repository for '{$this->project['name']}', cloning");
                if (file_exists($this->project['path']))
                {
                    $di = new \RecursiveDirectoryIterator($this->project['path'], \FilesystemIterator::SKIP_DOTS);
                    $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
                    foreach ($ri as $file)
                    {
                        $file->isDir() ? rmdir($file) : unlink($file);
                    }
                }
                $this->exec(
                    $this->prepareCmd("git", "clone  " . $this->getHookData('remote', 'repo_url') . " {$this->project['path']}"), "Unable to clone repo"
                );
            }
            // Else fetch changes
            else
            {

                $this->log("Fetching repository '{$this->project['name']}'");

                $this->exec(['cmd' =>
                    $this->prepareCmd("git", "fetch"), 'cwd' => $repoPath], "Unable to fetch repo"
                );

                if (!empty($out[1]) && stripos($out[1], "have diverged") !== false)
                {
                    $this->exec(['cmd' =>
                        $this->prepareCmd("git", "reset --hard HEAD", ""), 'cwd' => $this->project['path']], "Unable to reset head"
                    );
                    $this->exec(['cmd' =>
                        $this->prepareCmd("git", "clean -f -d", ""), 'cwd' => $this->project['path']], "Unable to git clean"
                    );
                    $this->exec(['cmd' =>
                        $this->prepareCmd("git", "pull", ""), 'cwd' => $this->project['path']], "Unable to pull from repo"
                    );
                }

            }
             $out = $this->exec($this->prepareCmd("git", "checkout -f {$this->project['branch']}", " cd $repoPath && GIT_WORK_TREE={$this->project['path']}"), "Unable to checkput repo");

            if (!empty($out[1]) && stripos($out[1], "Your branch is behind") !== false)
            {
                $this->exec(['cmd' =>
                    $this->prepareCmd("git", "pull", ""), 'cwd' => $this->project['path']], "Unable to pull from repo"
                );
            }

            $out = ($this->exec(['cmd' => $this->prepareCmd("git", "status", ""), 'cwd' => $this->project['path']], "Unable to check repo status"));

            if (empty($out[1]) || stripos($out[1], "On branch {$this->project['branch']}") === false)
            {
                throw new \Exception("Webhook failed");
            }

            $completed = true;

        }
        catch (ProcessFailedException $e)
        {
            $this->log($e->getMessage());
            throw new \Exception("Fetch from repo failed");
        }
        finally
        {
            if ($this->project['post_hook'])
            {
                $data['hook_data'] = $this->hookData;
                $data['project']   = $this->project;
                $data['hook']      = 'post';
                $data['status']    = $completed ? 1 : 0;
                $this->log("Runing post hook command:{$this->project['post_hook']} ");
                $out = $this->exec(['cmd' => $this->project['post_hook'], 'input' => json_encode($data)]);
                if (!$out[0])
                {
                    $this->log("Failed to run post hook script");
                }
            }

            if ($completed && $this->project['composer_update'])
            {

                $_ENV['HOME'] = realpath(__DIR__ . "/../../../");
                $_ENV['HOME'] = rtrim($_ENV['HOME'], "/") . "/cache";
                $this->log("Set composer HOME to " . $_ENV['HOME']);

                if (!file_exists($_ENV['HOME']))
                {
                    if (!mkdir($_ENV['HOME']))
                    {
                        throw new \Exception("Unable to create cache folder '{$_ENV['HOME']}'");
                    }
                    else
                    {
                        $this->log("Cache folder created ");
                    }
                }
                $this->exec(['cmd' =>
                    $this->prepareCmd("composer", "update", ""), 'cwd' => $this->project['path'], 'env' => $_ENV], "Unable to run composer update"
                );
            }
            if ($this->project['email_result'])
            {

                $this->log("Sending result to email:{$this->project['email_result']}  ");
                try
                {

                    $message = [
                        'subject'    => 'Git Auto deploy for ' . $this->project['name'] . '@' . $this->project['type'] . " was " . ($completed ? 'successfull' : 'failed'),
                        'body'       => "<code>--" . implode("<br/>--", $this->log) . "</code>",
                        'from_email' => $this->project['email_result'],
                        'from_name'  => '',
                    ];

                    $this->utils->mail($this->project['email_result'], $message, ['html' => true]);
                    $this->log("Email sent  ");

                }
                catch (\Exception $e)
                {
                    $this->log("Message could not be sent.");
                    $this->log('Mailer Error: ' . $e->getMessage());

                }

            }
            if (true == false && !empty($this->project['owner']) && count($owner = explode(":", $this->project['owner']) > 1))
            {
                $this->log("Setting owner of '{$this->project['path']}' to  {$this->project['owner']} ");
                $out = $this->exec("chown -R {$this->project['path']} {$this->project['owner']} ");
                if (!$out[0])
                {
                    $this->log("Failed to set folder owner");
                }

            }

            $fields = ['last_hook_status' => $completed ? 1 : 0, 'last_hook_duration' => microtime(true) - $this->startedon, 'last_hook_log' => implode("\n", $this->log)];
            $this->db->update($fields)
                ->table('projects')
                ->set($fields)
                ->where('id', '=', $this->project['id'])
                ->execute(false);

        }

    }

    private function exec($processes, $errorMessage = "")
    {
        $output    = [];
        $processes = is_array($processes) && isset($processes[0]) ? $processes : [$processes];

        try
        {
            foreach ($processes as $key => $command)
            {

                $cmd     = is_array($command) ? @$command['cmd'] : $command;
                $options = is_array($command) ? $command : [];
                unset($options['cmd']);
                if (!$cmd)
                {
                    throw new \Exception("invalid cmd");
                }
                $this->log("Running $cmd");

                $process_options = array_merge(['cwd' => null, 'env' => null, 'input' => null, 'options' => []], $options);
                $process         = new Process($cmd, @$process_options['cwd'], @$process_options['env'], @$process_options['input'], @$process_options['options']);

                $process->mustRun();

                //$process->wait();

                $this->log("Output:\n" . $process->getOutput());
                $output[$cmd] = $process->getOutput();
            }
            if (count($output) > 1)
            {
                return [1, ($output)];

            }
            else
            {
                $output = array_values($output);
                return [1, implode("\n", $output)];

            }
        }
        catch (ProcessFailedException $e)
        {
            $this->log($e->getMessage());
            if ($errorMessage)
            {
                throw new \Exception($errorMessage);
            }
            else
            {

                if (count($output) > 1)
                {
                    return [0, ($output)];

                }
                else
                {
                    return [0, $e->getMessage()];

                }
            }
        }
    }

    public function prepareCmd($bin, $cmd, $prepend = "")
    {
        if (empty($this->binpaths[$bin . "_path"]))
        {
            throw new Exception("Unable to find binary '$bin' location");
        }
        return "$prepend " . $this->binpaths[$bin . "_path"] . " $cmd";
    }

    public function ip_in_range($ip, $range)
    {
        if (is_array($range))
        {
            return in_array($range, $ip);
        }
        elseif (strpos($range, '/') == false)
        {
            $range .= '/32';
        }
        // $range is in IP/CIDR format eg 127.0.0.1/24
        list($range, $netmask) = explode('/', $range, 2);
        $range_decimal         = ip2long($range);
        $ip_decimal            = ip2long($ip);
        $wildcard_decimal      = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal       = ~$wildcard_decimal;
        return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }

    public function log($msg)
    {
        $this->log[] = $msg;
        $this->logger->info(is_array($msg) || is_object($msg) ? print_r($msg, 1) : $msg);
    }

    // testing
    // from main repo git checkout master; vi readme.html ; git add . ; git commit -m "xxxxxx"; git push origin HEAD
}
