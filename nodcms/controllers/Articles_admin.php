<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Articles_admin extends NodCMS_Controller {
    public $langArray = array();
	function __construct()
    {
        parent::__construct();
        $this->load->add_package_path(APPPATH."third_party/Articles");
        $this->load->model("Articles_model");
        $this->mainTemplate = "admin";
    }

    /**
     * Dashboard page
     */
    function dashboard()
    {
        $this->data['data_count'] = $this->Articles_model->getCount();
        echo json_encode(array(
            'status'=>"success",
            'content'=>$this->load->view($this->mainTemplate."/article_dashboard", $this->data, true)
        ));
    }

    /**
     * List of all articles
     */
    function article()
    {
        $this->data['title'] = _l("Articles",$this);
        $this->data['breadcrumb'] = array(
            array('title'=>_l('Articles',$this)),
        );

        $this->data['sub_title'] = _l("Sort",$this);
        $content_page = 'article_sort';
        $this->data['data_list']=$this->Articles_model->getAll(array('parent'=>0));
        foreach($this->data['data_list'] as &$item){
            $item['sub_data']=$this->Articles_model->getAll(array('parent'=>$item['article_id']));
        }

        $this->data['data_list'] = array_merge($this->Articles_model->getAll(array('parent'=>-1)),$this->data['data_list']);
        $this->data['page'] = "article_list";
        $this->data['content'] = $this->load->view($this->mainTemplate."/$content_page",$this->data,true);
        $this->load->view($this->frameTemplate,$this->data);
    }

    /**
     * Article edit/add form
     *
     * @param string $id
     */
    function articleForm($id=null)
    {
        $this->data['title'] = _l("Article",$this);
        $back_url = ARTICLES_ADMIN_URL."article";
        $self_url = ARTICLES_ADMIN_URL."articleForm";
        if($id!=null){
            $current_data = $this->Articles_model->getOne($id);
            if($current_data==null || count($current_data)==0){
                $this->systemError("The article couldn't find.",$back_url);
                return;
            }
            $self_url.="/$id";
            $this->data['sub_title'] = _l("Edit",$this);
            $form_attr = array();
        }else{
            $this->data['sub_title'] = _l("Add",$this);
            $form_attr = array('data-reset'=>1,'data-message'=>1);
        }

        $config = array(
            array(
                'field' => 'article_uri',
                'label' => _l("Document URI", $this),
                'rules' => 'required|callback_validURI|callback_isUnique[article,article_uri'.(isset($current_data)?",article_id,$current_data[article_id]":"").']',
                'type' => "text",
                'default'=>isset($current_data)?$current_data["article_uri"]:'',
                'help'=>_l("The unique text that will be in URL to open this article.", $this),
            ),
            array(
                'field' => 'name',
                'label' => _l("Article name", $this),
                'rules' => 'required',
                'type' => "text",
                'default'=>isset($current_data)?$current_data["name"]:''
            ),
            array(
                'field' => 'image',
                'label' => _l("Image", $this),
                'rules' => '',
                'type' => "image-library",
                'default'=>isset($current_data)?$current_data["image"]:''
            ),
            array(
                'field' => 'public',
                'label' => _l("Public", $this),
                'rules' => 'in_list[0,1]',
                'type' => "switch",
                'default'=>isset($current_data)?$current_data["public"]:''
            ),
            array(
                'label'=>_l('Page content',$this),
                'type'=>"h3",
            ),
        );
        $languages = $this->Public_model->getAllLanguages();
        foreach($languages as $language){
            $translate = $this->Articles_model->getTranslations($id, $language['language_id']);
            // Add language title
            array_push($config,array(
                'prefix_language'=>$language,
                'label'=>$language['language_title'],
                'type'=>"h4",
            ));
            $prefix = "translate[$language[language_id]]";
            array_push($config, array(
                'field'=>$prefix."[title]",
                'label'=>_l('Title',$this),
                'rules'=>"",
                'type'=>"text",
                'default'=>isset($translate['title'])?$translate['title']:'',
            ));
            array_push($config, array(
                'field'=>$prefix."[description]",
                'label'=>_l('Description',$this),
                'rules'=>"",
                'type'=>"textarea",
                'default'=>isset($translate['description'])?$translate['description']:'',
            ));
            array_push($config, array(
                'field'=>$prefix."[keywords]",
                'label'=>_l('Keywords',$this),
                'rules'=>"",
                'type'=>"textarea",
                'default'=>isset($translate['keywords'])?$translate['keywords']:'',
            ));
            array_push($config, array(
                'field'=>$prefix."[content]",
                'label'=>_l('Page Content',$this),
                'rules'=>"",
                'type'=>"texteditor",
                'default'=>isset($translate['content'])?$translate['content']:'',
            ));
        }

        $myform = new Form();
        $myform->config($config, $self_url, 'post', 'ajax');

        if($myform->ispost()){
            if(!$this->checkAccessGroup(1)){
                return;
            }
            $post_data = $myform->getPost();
            // Stop Page
            if($post_data === false){
                return;
            }

            if(key_exists('translate',$post_data)){
                $translates = $post_data['translate'];
                unset($post_data['translate']);
            }

            $URL = ARTICLES_ADMIN_URL."article/";
            $this->checkAccessGroup(1);
            if($id!=null){
                $this->Articles_model->edit($id, $post_data);
                if(isset($translates)){
                    $this->Articles_model->updateTranslations($id,$translates,$languages);
                }
                $this->systemSuccess("Article has been edited successfully.", $back_url);
            }
            else{
                $new_id = $this->Articles_model->add($post_data);
                if(isset($translates)){
                    $this->Articles_model->updateTranslations($new_id,$translates,$languages);
                }
                $this->systemSuccess("Article has been sent successfully.", $back_url);
            }
            return;
        }

        $this->data['breadcrumb'] = array(
            array('title'=>_l('Articles',$this),'url'=>ARTICLES_ADMIN_URL.'article'),
            array('title'=>$this->data['sub_title']),
        );

        $this->data['parents'] = $this->Articles_model->getAll(array('parent'=>0));
        $this->data['page'] = "article_form";
        $this->data['content']=$myform->fetch('', $form_attr);
        $this->load->view($this->frameTemplate,$this->data);
    }

    /**
     * Remove an article
     *
     * @param $id
     * @param int $confirm
     */
    function articleRemove($id, $confirm = 0)
    {
        if(!$this->checkAccessGroup(1))
            return;

        $back_url = ARTICLES_ADMIN_URL."article";
        $self_url = ARTICLES_ADMIN_URL."articleRemove/$id";
        $data = $this->Articles_model->getOne($id);
        if(count($data)==0){
            $this->systemError("The article couldn't find.", $back_url);
            return;
        }

        if($confirm!=1){
            echo json_encode(array(
                'status'=>'success',
                'content'=>'<p class="text-center">'._l("This action will delete the article from database.", $this).
                    '<br>'._l("After this, you will not to able to restore it.", $this).'</p>'.
                    '<p class="text-center font-lg bold">'._l("Are you sure to delete this?", $this).'</p>',
                'title'=>_l("Delete confirmation", $this),
                'noBtnLabel'=>_l("Cancel", $this),
                'yesBtnLabel'=>_l("Yes, delete it.", $this),
                'confirmUrl'=>"$self_url/1",
                'redirect'=>1,
            ));
            return;
        }

        $this->Articles_model->remove($id);
        $this->systemSuccess("The article has been deleted successfully.", $back_url);
    }

    /**
     * @param $id
     */
    function articleVisibility($id)
    {
        if(!$this->checkAccessGroup(1)){
            return;
        }
        $back_url = ARTICLES_ADMIN_URL."article";
        $data= $this->Articles_model->getOne($id);
        if($data == null || count($data)==0){
            $this->systemError("Couldn't find the article.", $back_url);
            return;
        }
        $public = $this->input->post('data');
        if($public == 1){
            $public = 0;
        }elseif($public == 0){
            $public = 1;
        }else{
            $this->systemError("Visibility value isn't correct. Please reload the page to solve this problem.", $back_url);
            return;
        }
        $update_data = array(
            'public'=>$public
        );
        $this->Articles_model->edit($id, $update_data);
        $this->systemSuccess("Success", ARTICLES_ADMIN_URL."article");
    }

    /**
     *
     */
    function articleSort()
    {
        if(!$this->checkAccessGroup(1)){
            return;
        }
        $i = 0;
        $index = 0;
        $parent = array(0);
        $children = array($this->input->post('data',TRUE));
        $children[$index] = json_decode($children[$index]);
        do{
            $data = $children[$index];
            foreach($data as $key=>$item){
                $i++;
                $update_data = array(
                    'order'=>$i,
                    'parent'=>$parent[$index]
                );
                $this->Articles_model->edit($item->id, $update_data);
                if(isset($item->children)){
                    $parent[$index+1] = $item->id;
                    $children[$index+1] = $item->children;
                }
            }
            $index++;
        }while(isset($children[$index]));
        $this->systemSuccess("Your articles has been successfully sorted.", ARTICLES_ADMIN_URL."article");
    }
}