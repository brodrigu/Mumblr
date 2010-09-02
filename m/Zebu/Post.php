<?php

class Zebu_Post extends Zebu_Document
{
    protected static $type = 'post';
    protected $requiredProperties = array(
        'title',
        'created',
        'body'
    );

}
