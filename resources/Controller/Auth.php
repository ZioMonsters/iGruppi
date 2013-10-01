<?php
/**
 * Description of Auth
 * 
 * @author gullo
 */
class Controller_Auth extends MyFw_Controller {
    
    
    function indexAction() {
        $this->forward("auth", "login");
    }
    
    function loginAction() {
        
        $form = new Form_Login();
        $form->setAction("/auth/login");
        
        // reset errorLogin
        $this->view->errorLogin = false;
        
        if($this->getRequest()->isPost()) {
            $fv = $this->getRequest()->getPost();
            if( $form->isValid($fv) ) {
                // check Auth
                $sql = "SELECT u.*, ug.attivo, ug.fondatore, ug.contabile, g.nome AS gruppo, g.idgroup "
                      ."FROM users AS u LEFT JOIN users_group AS ug ON u.iduser=ug.iduser "
                      ."LEFT JOIN groups AS g ON ug.idgroup=g.idgroup "
                      ."WHERE u.email= :email AND u.password= :password AND ug.attivo='S'";
                $checkSth = $this->getDB()->prepare($sql);
                $checkSth->execute(array('email' => $form->getValue("email"), 'password' => $form->getValue('password')));
                if( $checkSth->rowCount() > 0 ) {
                    // store user values
                    $auth = Zend_Auth::getInstance();
                    $auth->clearIdentity();
                    $storage = $auth->getStorage();
                    // remove password
                    $row = $checkSth->fetch(PDO::FETCH_OBJ);
                    $storage->write($row);
                    
                    // set idgroup in session
                    $userSessionVal = new Zend_Session_Namespace('userSessionVal');
                    $userSessionVal->idgroup = $row->idgroup;
                    
                    // redirect to Dashboard
                    $this->redirect('dashboard');
                    
                } else {
                    // Set ERROR: ACCOUNT NOT VALID!!
                    $this->view->errorLogin = "Email and/or Password are wrong!";
                }
            }
            //Zend_Debug::dump($sth); die;
            
        }
        // Zend_Debug::dump($form); die;
        // set Form in the View
        $this->view->form = $form;
        
    }

    function logoutAction() {
        $layout = Zend_Registry::get("layout");
        $layout->disableDisplay();

        Zend_Session::destroy();
        $this->redirect('index');
    }

    function registerAction() {

        $form = new Form_User();
        $form->setAction("/auth/register");
        // remove useless fields
        $form->removeField("iduser");
        $form->removeField("attivo");
        $form->removeField("fondatore");
        $form->removeField("contabile");

        // reset errorLogin
        $this->view->added = false;

        if($this->getRequest()->isPost()) {

            // get Post and check if is valid
            $fv = $this->getRequest()->getPost();
            if( $form->isValid($fv) ) {

                // ADD User
                $fValues = $form->getValues();
                if( $fValues["password"] != $fValues["password2"] ) {
                    $form->setError("password2", "Riscrivi correttamente la password");
                } else {
                    try {
                        // remove password2 field
                        unset($fValues["password2"]);
                        // get idgroup
                        $idgroup = $fValues["idgroup"];
                        unset($fValues["idgroup"]);

                        $gObj = new Model_Groups();
                        $group = $gObj->getGroupById($idgroup);
                        if($group !== false) {

                            // ADD USER
                            $iduser = $this->getDB()->makeInsert("users", $fValues);
                            
                            // ADD USER TO GROUP
                            $ugFields = array(
                                'iduser' => $iduser,
                                'idgroup'=> $idgroup
                            );
                            $this->getDB()->makeInsert("users_group", $ugFields);
                            
                            // Get Founder of the Group
                            $ugObj = $gObj->getGroupFoundersById($idgroup);
                            if(count($ugObj) > 0) {
                                // INVIO EMAIL al FONDATORE per NUOVO UTENTE
                                $mail = new MyFw_Mail();
                                $mail->setSubject("Nuovo iscritto al Gruppo ".$group->nome);
                                foreach($ugObj AS $ugVal) {
                                    $mail->addTo($ugVal["email"]);
                                }
                                $mail->setViewParam("new_user", $fValues["nome"] . " " . $fValues["cognome"] );
                                $mail->setViewParam("gruppo", $group->nome);
                                $mail->setViewParam("email_user", $fValues["email"]);
                                $this->view->sended = $mail->sendHtmlTemplate("notify.new_user_subscribe.tpl.php");
                            }

                            // OK!
                            $this->view->added = true;
                        } else {
                            $form->setError("idgroup", "Devi selezionare un gruppo esistente!");
                        }
                    } catch (Exception $exc) {
                        echo $exc->getTraceAsString();
                    }
                }
            }
        }

        // set Form in the View
        $this->view->form = $form;

    }
    
    function passwordAction() {
        
        // reset error
        $errorPwd = false;
        $this->view->sent = false;
        
        if($this->getRequest()->isPost()) {
            $fv = $this->getRequest()->getPost();
            $email = $fv["email"];
            if( $email != "" ) {
                $checkSth = $this->getDB()->prepare("SELECT iduser, nome, cognome FROM users WHERE email= :email");
                $checkSth->execute(array('email' => $email));
                if( $checkSth->rowCount() > 0 ) {
                    $user = $checkSth->fetch(PDO::FETCH_OBJ);

                    // generate new Password
                    $newPwd = substr(md5(time()), 0, 12);
                    
                    // INVIO NUOVA PASSWORD all'UTENTE
                    $mail = new MyFw_Mail();
                    $mail->setSubject("Cambio password!");
                    $mail->addTo($email);
                    $mail->setViewParam("newPwd", $newPwd );
                    $mail->setViewParam("nominativo", $user->nome . " " . $user->cognome );
                    $this->view->sent = $mail->sendHtmlTemplate("request.new_password.tpl.php");
                    if($this->view->sent) {
                        // aggiorno nuova password per utente
                        $arVal = array('iduser' => $user->iduser, 'password' => $newPwd);
                        $this->getDB()->makeUpdate("users", "iduser", $arVal);
                    } else {
                        $errorPwd = "Errore durante l'invio dell'email. Riprovare più tardi, grazie!";
                    }
                } else {
                    $errorPwd = "Indirizzo Email inesistente!";
                }
            } else {
                $errorPwd = "Inserisci un indirizzo email valido!";
            }
            
        }
        $this->view->errorPwd = $errorPwd;
        
    }
    
}