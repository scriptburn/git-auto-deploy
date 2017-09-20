<?php
namespace App\Services;

class RepoService
{
    private $db, $logger, $setting, $auth, $utils, $gitPath, $repoServices;

    public function __construct($db, $logger, $setting, $auth, $utils, $binpaths, $repoServices)
    {
        $this->db = $db;

        $this->logger  = $logger;
        $this->setting = $setting;
        $this->auth    = $auth;
        $this->utils   = $utils;

        $this->binpaths      = $binpaths;
        $this->repoServices = $repoServices;

    }
    public function findProject($name, $type = "")
    {
        $dbObj = $this->db->select()
            ->from('projects')
            ->where('name', "=", $name);
        if ($type)
        {
            $dbObj = $dbObj->where('type', "=", $type);
        }

        $dbObj = $dbObj->execute();
        while ($row = $dbObj->fetch())
        {
            $repo[] = $row;
        }
        if (!$repo)
        {
            throw new \Exception("Repo with name '$name' not found ");
        }
        return $repo;

    }

    public function identifyandValidateRepo($data, $request)
    {

    
        foreach ($this->repoServices as $repoService)
        {

            if ($repoService->identify($data))
            {

                $repoService->init($this, $this->setting, $this->auth, $this->logger, $this->db, $this->utils, $request, $this->binpaths);
                $repoService->validate();
                $repoService->sync();
                return $repoService;
            }
        }

        return false;
    }

}
