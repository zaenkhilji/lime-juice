<?php
/**
 * @author: Mihai Petcu mihai.costin.petcu@gmail.com
 * @date: 10.11.2015
 */
use Phalcon\Mvc\View;

class SettingsController extends ControllerBase
{
    /**
     * Unset isSecure for this controller. To be defined in each method.
     */
    public function initialize(){
        $this->isSecure = false;
        parent::initialize();
        $this->allowRoles();
    }

    public function updateAction(){
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
    }

    /**
     * Put method inside a controller
     */
    public function testAction(){
        echo 'Hello world';
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER); //will stop rendering view
    }

    /**
     * Settings controller. List all users and create new user!
     */
    public function indexAction(){
        //list all users
        $this->view->users = User::find();

        //form for a user
        if($this->securePage(true)) {
            $user = new User();

            //setPass
            $passString = substr(sha1(microtime()),0,10);
            $user->setPass($passString);

            if($this->processForm($user, 'UserRequestForm')) {
                $this->mail->addAddress($this->request->get('email'));
                $this->mail->Subject = 'New account [Report manager]';
                $this->mail->Body = 'A new user on Report manager was created with your email address!<br><br/>' .
                    '<i>Username</i>: <b>' . $user->email . '</b><br/>' .
                    '<i>Password</i>: <b>' . $passString . '</b><br/><br/>' .
                    'You can access your account here: <a href="' . $this->url->get('/') . '">' . $this->url->get('/') . '</a>';
                $this->mail->AltBody = strip_tags($this->mail->Body);

                if ($this->mail->send())
                    $this->flash->success("A new user request is sent to <b>" . $this->request->get('email') . "</b>.");
                else
                    $this->flash->error("A new user is created but email could not be sent to <b>" . $this->request->get('email') . "</b>.");

                return $this->response->redirect('settings/index');
            }
        }
    }

    /**
     * Will run only at startup if no users defined
     */
    public function installAction()
    {
        if(count(User::find()))
            return $this->response->redirect();

        $this->view->showMenu = false;
        $errorMessage = [];

        if(!(is_readable('reports') && is_writable('reports'))){
            $this->view->permission = ['status' => false, 'message' => "Directory: <b>[YourProjectRoot]/public/reports</b> does not have permission to read, write and delete.<br/>"];
            $errorMessage[] = "You can't install without permission to read, write and delete for directory: <b>[YourProjectRoot]/public/reports</b>.<br/>";
        }else{
            $this->view->permission = ['status' => true, 'message' => 'Directory: <b>[YourProjectRoot]/public/reports</b> has right permissions.'];
        }

        //Mongo check connection
        if($this->mongo instanceof Exception){
            $this->view->mongo = ['status' => false, 'message' => $this->mongo->getMessage()];
            $errorMessage[] = "You can't install without a functional Mongo connection!<br/>";
        }else{
            $this->view->mongo = ['status' => true, 'message' => 'Mongo connection succeed.'];
        }

        //Mail check SMTP
        if($this->mail instanceof Exception){
            $this->view->mail = ['status' => false, 'message' => $this->mail->getMessage()];
            $errorMessage[] = $this->mail->getMessage();
        }else{
            $this->view->mail = ['status' => true, 'message' => 'Mail service connection succeed.'];
        }

        if($this->request->isPost()){
            $post = $this->request->getPost();
            if($this->utility->isEmailValid($post['conf']['master_user']) && $post['conf']['master_pass'] == $post['conf']['master_pass2']){

                //save user;
                $user = new User();
                $user->email = $post['conf']['master_user'];
                $user->type = 'master';
                $user->setPass($post['conf']['master_pass']);
                $user->save();

                //change install.txt;
                $fp = fopen('reports/install.txt', 'w');
                $str = file_get_contents('reports/install.txt');
                $str = str_replace('install=none;', 'install=done;', $str);
                fwrite($fp, $str);
                fclose($fp);
                return $this->response->redirect('index/index');
            }
        }
    }

    public function changeUserStatusAction(){
        $user = User::findById($this->request->get('id'));
        if($user){
            $user->setStatus($user->status?0:1);
            $user->save();
            echo $user->status;

        }
        $this->view->disable();
    }

    public function changeUserTypeAction(){
        $user = User::findById($this->request->get('id'));
        if($user){
            $user->type = $this->request->get('type');
            $user->save();
            echo $user->type;

        }
        $this->view->disable();
    }

    public function changeUserPassAction(){
        $user = User::findById($this->request->get('id'));
        if($user){
            $passString = substr(sha1(microtime()),0,10);
            $user->setPass($passString);
            $user->save();

            //send email
            $this->mail->addAddress($user->email);
            $this->mail->Subject = 'Reset password [Report manager]';
            $this->mail->Body = 'A new password has been generated for your account:<br><br/>' .
                '<b>' . $passString . '</b><br/><br/>' .
                'You can access your account here: <a href="' . $this->url->get('/') . '">' . $this->url->get('/') . '</a>';
            $this->mail->AltBody = strip_tags($this->mail->Body);

            if ($this->mail->send())
                echo 'Sent pass';
            else
                echo 'Error';
        }
        $this->view->disable();
    }

    /**
     * Manage permission for an defined user
     */
    public function permissionModalAction(){
        $db = Db::find();
        $user = User::findById($this->request->get('id'));
        $this->view->dbm = $db;
        $this->view->user = $user;
    }

    /**
     * Manage permission for an defined report
     */
    public function permissionUserModalAction(){
        $users = User::find(['conditions' => ['type' => 'operator', 'status' => 1]]);
        $report = Report::findById($this->request->get('id'));
        $this->view->report = $report;
        $this->view->users = $users;
    }

    /**
     * Change and assign permision for a report of for an user
     * Will always return integer "1"
     */
    public function changePermissionAction(){

        //case of permission per report
        if( $this->request->get('report') ){
            $report = Report::findById($this->request->get('report'));
            $users = User::find(['conditions' => ['type' => 'operator', 'status' => 1]]);
            $perm = $this->request->get('perm');
            foreach($users as $user){
                if(isset($perm[$user->getId()]) && in_array('view', $perm[$user->getId()])){
                    $user->setPermission('Report', $report->getId(), $perm[$user->getId()]);
                }else{
                    $user->unsetPermission('Report', $report->getId(), $perm[$user->getId()]);
                }
                $user->save();
            }
        }

        //case of permission per user
        if( $this->request->get('user') ) {
            $user = User::findById($this->request->get('user'));
            $user->removePermissions();
            if ($perm = $this->request->get('perm')) {
                foreach ($perm as $key => $val) {
                    $user->setPermission('Report', $key, $val);
                }
            }
            $user->save();
        }

        echo 1;
        $this->view->setRenderLevel(View::LEVEL_NO_RENDER);
    }

}

