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
                      ."WHERE u.email= :email";
                $checkSth = $this->getDB()->prepare($sql);
                $checkSth->execute(array('email' => $form->getValue("email")));
                if( $checkSth->rowCount() > 0 ) 
                {
                    // Fetch User data
                    $row = $checkSth->fetch(PDO::FETCH_OBJ);
                    if($row->password == $form->getValue("password") &&
                       $row->attivo == "S" )
                    {
                        // store user values
                        $auth = Zend_Auth::getInstance();
                        $auth->clearIdentity();
                        $storage = $auth->getStorage();
                        // remove password & write data to the store
                        unset($row->password);
                        $storage->write($row);

                        // set idgroup in session
                        $userSessionVal = new Zend_Session_Namespace('userSessionVal');
                        $userSessionVal->idgroup = $row->idgroup;
                        
                        // set ACL User in session
                        $aclUserObj = new Model_AclUser($row->fondatore, $row->contabile);
                        $userSessionVal->aclUserObject = $aclUserObj;
                        
                        // redirect HTTP_REFERER if it comes from a different URI
                        if(strpos($_SERVER["HTTP_REFERER"], "auth/login") === false)
                        {
                            header("Location: " . $_SERVER["HTTP_REFERER"]);
                            exit;
                        } else {
                            $this->redirect('dashboard');
                        }
                    } else {
                        // Set ERROR: ACCOUNT NOT VALID!!
                        $this->view->errorLogin = "Password non corretta o Account non ancora abilitato.";
                    }
                } else {
                    // Set ERROR: ACCOUNT NOT VALID!!
                    $this->view->errorLogin = "Email non corretta o inesistente!";
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
        $form->removeField("in_prova");
        $form->removeField("attivo");
        $form->removeField("fondatore");
        $form->removeField("contabile");
        $form->removeField("tel");
        $form->removeField("indirizzo");
        $form->removeField("localita");
        $form->removeField("note");

        // SET registration_type default value
        $form->setValue("registration_type", "0");

        // reset errorLogin
        $this->view->added = false;

        if($this->getRequest()->isPost()) {

            // get Post and check if is valid
            $fv = $this->getRequest()->getPost();
            if( $form->isValid($fv) ) 
            {
                // GET FORM VALUES
                $fValues = $form->getValues();

                // unset not used fields
                $group_nome = $fValues["group_nome"];
                unset($fValues["group_nome"]);
                $group_desc = $fValues["group_desc"];
                unset($fValues["group_desc"]);
                $provincia = $fValues["provincia"];
                unset($fValues["provincia"]);

                //print_r($fValues);die;
                // check EMAIL DUPLICATI
                $checkSth = $this->getDB()->prepare("SELECT email FROM users WHERE email= :email");
                $checkSth->execute(array('email' => $fValues["email"]));
                if( $checkSth->rowCount() > 0 ) 
                {
                    $form->setError("email", "Questo indirizzo email è stato già utilizzato!");
                } else {
                    if( $fValues["password"] != $fValues["password2"] ) {
                        $form->setError("password2", "Riscrivi correttamente la password");
                    } else {
                        // remove password2 field
                        unset($fValues["password2"]);

                        // get registration_type
                        $registration_type = $fValues["registration_type"];
                        unset($fValues["registration_type"]);

                        $gObj = new Model_Db_Groups();

                        // check registration_type
                        if($registration_type == "0") {
                        # GAS ESISTENTE
                            $idgroup = $fValues["idgroup"];
                            unset($fValues["idgroup"]);
                            $group = $gObj->getGroupById($idgroup);
                            if($group === false) {
                                $form->setError("idgroup", "Devi selezionare un gruppo esistente!");
                            }

                            // array users_group data
                            $ugFields = array(
                                'idgroup'=> $idgroup
                            );

                        } else {
                        # NUOVO GAS
                            unset($fValues["idgroup"]);
                            // ADD NEW GROUP
                            if($group_nome == "") {
                                $form->setError("group_nome", "Devi inserire un nome del gruppo!");
                            }
                            if($provincia == "") {
                                $form->setError("provincia", "Devi inserire la provincia del gruppo!");
                            }
                            if(!$form->hasErrors()) {
                                $gFields = array(
                                    'nome' => $group_nome,
                                    'descrizione' => $group_desc,
                                    'provincia' => $provincia,
                                    'data_creazione' => date("Y-m-d"),
                                    'email_ml' => ''
                                );
                                $idgroup = $this->getDB()->makeInsert("groups", $gFields);

                                // array users_group data
                                $ugFields = array(
                                    'idgroup'=> $idgroup,
                                    'attivo' => 'S',
                                    'fondatore' => 'S'
                                );
                            }
                        }

                        // check if ERROR exists
                        if(!$form->hasErrors()) {
                            try {
                                // ADD USER
                                $iduser = $this->getDB()->makeInsert("users", $fValues);

                                // ADD iduser TO users_group array data
                                $ugFields['iduser'] = $iduser;
                                $this->getDB()->makeInsert("users_group", $ugFields);

                                if($registration_type == "0") {

                                    // Get Founder of the Group
                                    $ugObj = $gObj->getGroupFoundersById($idgroup);
                                    if (count($ugObj) > 0) {
                                        // INVIO EMAIL al FONDATORE per NUOVO UTENTE
                                        $mail = new MyFw_Mail();
                                        $mail->setSubject("Nuovo iscritto al Gruppo " . $group->nome);
                                        foreach ($ugObj AS $ugVal) {
                                            $mail->addTo($ugVal["email"]);
                                        }
                                        $mail->setViewParam("new_user", $fValues["nome"] . " " . $fValues["cognome"]);
                                        $mail->setViewParam("gruppo", $group->nome);
                                        $mail->setViewParam("email_user", $fValues["email"]);
                                        $config = Zend_Registry::get("appConfig");
                                        $mail->setViewParam("url_environment", $config->url_environment);
                                        $this->view->sended = $mail->sendHtmlTemplate("registration.new_user_subscribe.tpl.php");
                                    }
                                }

                                // REGISTRATION OK!
                                $this->redirect("auth", "registerok");

                            } catch (MyFw_Exception $exc) {
                                $exc->displayError();
                            }
                        }
                    }
                }
            }
        }
        // set Form in the View
        $this->view->form = $form;
    }
    
    function registerokAction() {
        
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