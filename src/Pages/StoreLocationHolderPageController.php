<?php
namespace Antlion\StoreLocation\Pages;

use PageController;
    class StoreLocationHolderPageController extends PageController
    {
        private static array $allowed_actions = [];
        protected function init()
            {
                parent::init();
                // You can include any CSS or JS required by your project here.
                // See: https://docs.silverstripe.org/en/developer_guides/templates/requirements/
            }
    }