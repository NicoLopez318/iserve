<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function requireCondition($condition, int $code=422, string $message='This action is unavailable. Check the record, schedule, and review status.'):void
    {
        if($condition)return;
        if($code===422)throw \Illuminate\Validation\ValidationException::withMessages(['workflow'=>$message]);
        abort($code,$message);
    }
    protected function rejectIf($condition,int $code=422,string $message='This action is unavailable. Check the record, schedule, and review status.'):void
    {
        $this->requireCondition(!$condition,$code,$message);
    }
}
