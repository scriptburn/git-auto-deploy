<?php
namespace App\Services;

class BitBucketService extends GitService
{

    private $valid_bb_ips = ['104.192.143.0/24', '34.198.203.127', '34.198.178.64', '34.198.32.85'];

    public function identify($data)
    {
        if (isset($data['headers']['HTTP_USER_AGENT'][0]) && stripos($data['headers']['HTTP_USER_AGENT'][0], 'Bitbucket-Webhooks') !== false)
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
        $index = 'old';
        if (empty($this->hookData['post']['push']['changes'][0]['old']['name']))
        {
            $index = 'new';
        }
        if (empty($this->hookData['post']['push']['changes'][0][$index]['name']))
        {
            throw new \Exception("Unknown remote branch");
        }
        elseif (@($this->hookData['post']['push']['changes'][0][$index]['type']) != 'branch')
        {
            throw new \Exception("Expecting remote branch");

        }

        $this->hookData['remote']['branch_name'] = $this->hookData['post']['push']['changes'][0][$index]['name'];

    }
    public function parseRemoteUrl()
    {
        if (empty($this->project['name']))
        {
            throw new \Exception("Unknown repo name to track");
        }
        $name = explode("/", $this->project['name']);
        if (empty($name[0]))
        {
            throw new \Exception("Invalid repo name format");
        }
        $this->hookData['remote']['repo_url'] = "git@bitbucket.org:/{$this->project['name']}.git";
    }
    public function repoType()
    {
        return "bb";
    }
    public function validate()
    {
        $curent_ip = $this->request->getAttribute('ip_address');
        $valid_ip  = false;
        foreach ($this->valid_bb_ips as $ip)
        {
            if ($this->ip_in_range($curent_ip, $ip))
            {
                $valid_ip = true;
                break;
            }
        }
        if (!$valid_ip)
        {
            //throw new \Exception("Webhook originated from unknown bitbucket ip:$curent_ip, Valie ip range:" . implode(",", $this->valid_bb_ips));
        }

    }

    public function test()
    {
        /*
    rm -rf /Volumes/MacData/htdocs/test/git-auto-deploy-test-bb;
    git clone git@bitbucket.org:rajneeshojha/test.git /Volumes/MacData/htdocs/test/git-auto-deploy-test-bb

     */
    }
}
