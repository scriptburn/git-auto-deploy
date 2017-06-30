<?php
namespace App\Services;

 class GitHubService extends GitService
{
    public function identify($data)
    {
        if (isset($data['headers']['HTTP_X_GITHUB_EVENT']))
        {
            $this->hookData = $data;

            return true;
        }
        return false;
    }
    public function parseRemoteRepoName()
    {
        if (empty($this->hookData['post']['repository']['full_name']))
        {
            throw new \Exception('Repositry name not found');
        }
        $this->hookData['remote']['repo_name'] = $this->hookData['post']['repository']['full_name'];
    }
    public function parseRemoteBranchName()
    {
        if (empty($this->hookData['post']['ref']))
        {
            throw new \Exception("Unknown remote branch");
        }
        $ref                                     = explode("/", $this->hookData['post']['ref']);
        $this->hookData['remote']['branch_name'] = $ref[2];
    }
    public function parseRemoteUrl()
    {
        if (empty($this->project['name']))
        {
            throw new \Exception("Unknown repo name to track");
        }

        $this->hookData['remote']['repo_url'] = "git@github.com:/{$this->project['name']}.git";

    }
    public function repoType()
    {

        return "gh";
    }
    public function validate()
    {

        if (isset($this->hookData['headers']['HTTP_X_HUB_SIGNATURE']))
        {
            if (!$this->project['secret'])
            {
                throw new \Exception('Secret has been set in repositry but secret was not entred in  project');

            }
            elseif (!extension_loaded('hash'))
            {
                throw new \Exception('Has Extension missing');
            }
            list($algo, $hash) = explode('=', $this->hookData['headers']['HTTP_X_HUB_SIGNATURE'][0], 2) + array('', '');
            if (!in_array($algo, hash_algos(), true))
            {
                throw new \Exception("Hash algorithm '$algo' is not supported.");
            }
            elseif ($hash !== hash_hmac($algo, $this->hookData['body'], $this->project['secret']))
            {
                throw new \Exception('Hook secret does not match.');
            }
        }
    }
}
