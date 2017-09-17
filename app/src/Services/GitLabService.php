<?php
namespace App\Services;

class GitLabService extends GitService
{

    public function identify($data)
    {
        if (isset($data['headers']['HTTP_X_GITLAB_EVENT'][0]))
        {
            $this->hookData = $data;

            return true;
        }
        return false;
    }
    public function parseRemoteRepoName()
    {
        if (empty($this->hookData['post']['project']['path_with_namespace']))
        {
            throw new \Exception('Repositry name not found');
        }
        $this->hookData['remote']['repo_name'] = $this->hookData['post']['project']['path_with_namespace'];
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

        $this->hookData['remote']['repo_url'] = "git@gitlab.com:/{$this->project['name']}.git";

    }
    public function repoType()
    {

        return "gl";
    }
    public function validate()
    {

        if (isset($this->hookData['headers']['HTTP_X_GITLAB_TOKEN'][0]))
        {
            if (!$this->project['secret'])
            {
                throw new \Exception('Secret has been set in repositry but secret was not entred in  project');

            }
            elseif ($this->project['secret'] != $this->hookData['headers']['HTTP_X_GITLAB_TOKEN'][0])
            {
                throw new \Exception('Hook secret does not match.');
            }
        }
    }
}
