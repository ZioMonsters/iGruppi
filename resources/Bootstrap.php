<?php
/**
 * Description of Bootstrap
 * 
 * @author gullo
 */
class Bootstrap {
    
    public function run() {
        $this->startConfigIni();
        $this->startPhpSettings();
        $this->startLocale();
        $this->startDB();
        $this->startLayout();
        $this->startView();
        $this->startPlugin();
    }

    private function startConfigIni () {
        $appConfig = new Zend_Config_Ini(APPLICATION_PATH . '/resources/Config/application.ini', APPLICATION_ENV);
        Zend_Registry::set('appConfig', $appConfig);
    }
    
    private function startPhpSettings () {
        ini_set("display_errors", Zend_Registry::get("appConfig")->phpSettings->display_errors);
        ini_set("display_startup_errors", Zend_Registry::get("appConfig")->phpSettings->display_startup_errors);
    }  
    
    private function startDB () {
        $db = new MyFw_DB();
        Zend_Registry::set('db', $db);
    }
    
    private function startLocale () {
    	date_default_timezone_set('Europe/Rome');
    	setlocale(LC_TIME, 'it_IT');
    }
    
    private function startLayout() {
        $l = new MyFw_Layout();
        Zend_Registry::set('layout', $l);
    }

    private function startView() {
        $view = new MyFw_View();
        Zend_Registry::set('view', $view);
    }

    private function startPlugin() {
        $pObj = new MyFw_Plugin();
        $pObj->loadPlugin('Auth');
        Zend_Registry::set('plugin', $pObj);
    }

}