<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('users',function(Blueprint $t){$t->string('employee_id')->nullable();$t->string('ward_name')->nullable();$t->string('ward_student_id')->nullable();});}public function down():void{Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['employee_id','ward_name','ward_student_id']));}};
