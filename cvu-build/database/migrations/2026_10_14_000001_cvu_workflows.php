<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('users',fn(Blueprint $t)=>$t->timestamp('values_verified_at')->nullable());
  Schema::table('assignments',function(Blueprint $t){$t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('approved_at')->nullable();});
  Schema::table('events',fn(Blueprint $t)=>$t->json('metadata')->nullable());
 }
 public function down():void {
  Schema::table('events',fn(Blueprint $t)=>$t->dropColumn('metadata'));
  Schema::table('assignments',function(Blueprint $t){$t->dropConstrainedForeignId('approved_by');$t->dropColumn('approved_at');});
  Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('values_verified_at'));
 }
};
