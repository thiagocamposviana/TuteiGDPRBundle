<?php

namespace Tutei\GDPRBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction( $template )
    {
        return $this->render( $template );
    }
}
