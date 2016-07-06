<?php
class API extends RESTful
{

    /* @var $renderTwig RenderTwig */
    var $renderTwig;
    /* @var $twig Twig_Environment */
    var $twig;

    /**
     * Library of errors. use /__codes to show the list
     */
    function __codes()
    {
        $this->addCodeLib('system', 'About system error');
        $this->addCodeLib('system-twig', 'Error in twig render library');

    }

    /**
     * Main method to control the API
     */
    function main()
    {
        $this->renderTwig = $this->core->loadClass('RenderTwig');
        if($this->renderTwig->error) $this->setError($this->getCodeLib('system-twig'),503,'system-twig');

        $this->renderTwig->addStringTemplate('test','Hello {{ name }}! wapploca:universe;earth;salutations;hello in "{{ getLang() }}" = {{ l("test","universe;earth;salutations;hello")}}');
        $this->renderTwig->setTwig('test');
        if(!$this->error) $this->addReturnData(['hello'=>$this->renderTwig->render(array('name'=>'Lola'))]);

        $this->renderTwig->addStringTemplate('test','Bye {{ name }}! wapploca:universe;earth;salutations;good_bye in "{{ getLang() }}" = {{ l("test","universe;earth;salutations;good_bye")}}');
        $this->renderTwig->setTwig('test');
        if(!$this->error) $this->addReturnData(['hello'=>$this->renderTwig->render(array('name'=>'Lola'))]);

    }

}