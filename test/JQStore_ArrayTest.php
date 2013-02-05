<?php

// @todo factor out JQStore_Array specific tests into this file

require_once dirname(__FILE__) . '/TestCommon.php';
require_once dirname(__FILE__) . '/JQStore_AllTest.php';

class JQStore_ArrayTest extends JQStore_AllTest
{
    function setup()
    {
        $this->jqStore = new JQStore_Array;
    }
}
