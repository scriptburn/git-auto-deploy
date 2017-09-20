<?php
namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class ProjectsController
{
    private $db, $flash, $router, $view, $paging, $utils, $project_types, $project_status, $auth;

    public function __construct($view, $db, $flash, $router, $paging, $session, $utils, $auth)
    {
        $this->view    = $view;
        $this->db      = $db;
        $this->flash   = $flash;
        $this->router  = $router;
        $this->paging  = $paging;
        $this->session = $session;
        $this->utils   = $utils;
        $this->auth    = $auth;

        $this->project_types  = ['gh' => 'GitHub', 'bb' => 'BitBucket','gl'=>'GitLab'];
        $this->project_status = [0 => 'Disabled', 1 => 'Active'];

    }

    function listAll(Request $request, Response $response, $args)
    {
        $args            = array_merge($request->getQueryParams(), is_array($request->getParsedBody()) ? $request->getParsedBody() : [], $args);
        $params['query'] = $request->getQueryParams();

        $dbQuery = $this->db->select()->from("projects");
        if (isset($params['query']['search_text']) && $params['query']['search_text'])
        {
            $dbQuery = $dbQuery->where('name', "=", $params['query']['search_text']);

        }
        if (isset($params['query']['project_type']) && $params['query']['project_type'])
        {
            $dbQuery = $dbQuery->where('type', "=", $params['query']['project_type']);

        }

        if (isset($params['query']['project_status']) && $params['query']['project_status'] != "")
        {
            $dbQuery = $dbQuery->where('status', "=", $params['query']['project_status']);

        }
        if (!$this->auth->is_admin('role'))
        {
            $dbQuery = $dbQuery->where('uid', "=", $this->auth->user('id'));
        }

        $countQuery    = clone $dbQuery;
        $dataQuery     = clone $dbQuery;
        $project_count = $countQuery->count("id")->execute()->fetch();

        $pagination = $this->paging->page($project_count['COUNT( id )']);

        $projecs = $dataQuery->orderby('id', 'desc')->limit($pagination['pagination']['limit'], $pagination['pagination']['skip'])->execute();

        $params['projects']       = $projecs;
        $params['pager']     = $pagination;
        $params['project_types']  = $this->project_types;
        $params['project_status'] = $this->project_status;
 
         return $this->view->render($response, 'projects.twig', $params);

    }
    public function form(Request $request, Response $response, $args)
    {
        $params  = array_merge($request->getQueryParams(), is_array($request->getParsedBody()) ? $request->getParsedBody() : [], $args);
        $project = [];
        if (isset($params['id']))
        {
            $project = $this->db->select()->from("projects")->where('id', '=', $params['id'])->execute()->fetch();

        }
        $validate = function ($params)
        {
            if (empty(trim($params['type'])))
            {
                throw new \Exception('Please strim(elect repository type');
            }
            elseif (empty(trim($params['name'])))
            {
                throw new \Exception('Please enter repository name');

            }
            elseif (empty(trim($params['path'])))
            {
                throw new \Exception('Please enter local repository path');

            }

        };
        if ($request->isPost())
        {
            try
            {

                if (isset($params['id']) && $params['id'] && !$project)
                {
                    throw new \Exception('Invalid project id');
                }
                $validate($params);
                $project_exists = $this->db
                    ->select()
                    ->from("projects")
                    ->where('name', '=', trim($params['name']))
                    ->where('type', '=', trim($params['type']))
                    ->where('branch', '=', trim($params['branch']));
                $path_exists = $this->db
                    ->select()
                    ->from("projects")
                    ->where('path', '=', trim($params['path']));

                if ($project)
                {
                    $project_exists = $project_exists->whereNotIn('id', array($params['id']));
                    $path_exists    = $path_exists->whereNotIn('id', array($params['id']));

                }

                $project_exists = $project_exists->execute()->fetch();
                $path_exists    = $path_exists->execute()->fetch();

                if ($path_exists)
                {
                    throw new \Exception('Local path is already in use');
                }
                elseif ($project_exists)
                {
                    throw new \Exception('Duplicate project');
                }

                $fields = [

                    'name'         => trim($params['name']),
                    'type'         => trim($params['type']),
                    'branch'       => trim($params['branch']),
                    'path'         => trim($params['path']),
                    'owner'        => trim($params['owner']),
                    'status'       => (int) ($params['status']),
                    'secret'       => ($params['secret']),
                    'pre_hook'     => trim($params['pre_hook']),
                    'post_hook'    => trim($params['post_hook']),
                    'email_result' => ($params['email_result']),
                    'composer_update' => (int) @($params['composer_update']),
                ];

                if ($project)
                {
                    $this->db->update($fields)
                        ->table('projects')
                        ->set($fields)
                        ->where('id', '=', $params['id'])
                        ->execute(false);
                }
                else
                {
                    $this->db->insert(array_merge(['uid'], array_keys($fields)))
                        ->into('projects')
                        ->values(array_merge([$this->auth->user('id')], array_values($fields)))
                        ->execute(false);

                    $params['id'] = $this->db->lastInsertId();
                }

                $this->flash->addMessage('success', sprintf('Project %1$s successfully', $project ? 'updated' : 'created'));
                $this->flash->addMessage('form', $request->getParsedBody());

                return $response->withRedirect($this->router->pathFor('project_form', ['id' => $params['id']]));

            }
            catch (\Exception $e)
            {
                $this->flash->addMessage('error', $e->getMessage());
                $this->flash->addMessage('form', $request->getParsedBody());
                return $response->withRedirect($this->utils->urlFor('project_form', isset($params['id']) && $params['id'] ? ['id' => $params['id']] : []));
            }

        }
        else
        {
            if (isset(($params['id'])) && $params['id'] && !$project)
            {
                $this->flash->addMessage('error', 'Invalid project id');
                return $response->withRedirect($this->router->pathFor('projects'));
            }

            $project_types = ['gh' => 'GitHub', 'bb' => 'BitBucket'];
            $params        = [
                'form'           => $project,
                'project_types'  => $this->project_types,
                'project_status' => $this->project_status,

                'title'          => $project ? 'Edit project: ' . $project['name'] : 'Create project',
            ];
            $this->view->render($response, 'project_form.twig', $params);
        }

    }

    public function search(Request $request, Response $response, $args)
    {
        $params  = array_merge($request->getQueryParams(), is_array($request->getParsedBody()) ? $request->getParsedBody() : [], $args);
        $project = [];
        $url     = ($this->utils->urlFor('project_list'));

        if (isset($params['reset']))
        {
            return $response->withRedirect($url);

        }
        elseif (!empty($params['search_text']) || (isset($params['project_status']) && $params['project_status'] != "") || (isset($params['project_type']) && $params['project_type'] != ""))
        {
            $url = $this->utils->makeUrl($this->utils->UrlFor('project_list'), ['search_text' => $params['search_text'], 'project_status' => $params['project_status'], 'project_type' => $params['project_type']]);
        }

        return $response->withRedirect($url);

    }
    public function delete(Request $request, Response $response, $args)
    {
        $params  = array_merge($request->getQueryParams(), is_array($request->getParsedBody()) ? $request->getParsedBody() : [], $args);
        $project = $this->db->delete()
            ->from('projects')
            ->where('id', "=", $params['id']);

        if (!$this->auth->is_admin('role'))
        {
            $project = $project->where('uid', '=', $this->auth->user('id'));
        }

        $project->execute(false);
        $this->flash->addMessage('success', 'Project deleted');

        return $response->withRedirect($this->utils->urlFor('project_list'));
    }
}
