<?php

class ContactController extends ContactControllerCore
{
    public function postProcess()
    {
        Hook::exec('actionBeforeContactSubmit');

        if (!sizeof($this->context->controller->errors)) {
            parent::postProcess();
        }
    }
}
