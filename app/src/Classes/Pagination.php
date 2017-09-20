<?php
namespace App\Classes;

class Pagination
{
    private $request,$view, $per_page;

    public function __construct($view,$request, $utils,$per_page = 5)
    {
        $this->request  = $request;
        $this->view  = $view;
                $this->utils  = $utils;

        $this->per_page = $per_page > 0 ? $per_page : 5;
    }
    public function page($count,$current_page = 0, $per_page = 0 )
    {
        $page  = $current_page && $current_page > 0 ? $current_page : (($this->request->getParam('page', 0) > 0) ? $this->request->getParam('page') : 1);
        $limit = $per_page ? $per_page : $this->per_page;
        $skip  = ($page - 1) * $limit;

        $params['pagination'] = [
            'needed'   => $count > $limit,
            'count'    => $count,
            'page'     => $page,
            'lastpage' => (ceil($count / $limit) == 0 ? 1 : ceil($count / $limit)),
            'limit'    => $limit,
            'skip'    => $skip,
            'url'=>$this->utils->currentUrl()
        ];
        
        $params['paging']=$this->view->fetch(  'paging.twig',$params);
        return $params;
    }

}
