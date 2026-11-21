<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::create('reports',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained();$t->string('title');$t->string('academic_year');$t->string('semester');$t->text('summary');$t->json('snapshot');$t->string('status')->default('pending');$t->text('review_note')->nullable();$t->foreignId('reviewed_by')->nullable()->constrained('users');$t->timestamps();});}public function down():void{Schema::dropIfExists('reports');}};
