<?php

class Zebu_User extends Zebu_Document
{
    protected static $type = 'user';
    protected $requiredProperties = array(
        'username',
        'passhash',
    );

}
