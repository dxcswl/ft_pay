<?php

    function Ipt_check($receipt, $isSandbox){
        $Ipt = new \ft\Ipt();
        return $Ipt->getIapReceiptData($receipt, $isSandbox);
    }
