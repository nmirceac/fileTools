<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->increments('id');
            $table->string('hash', 32)->index();
            $table->string('name')->index();
            $table->string('extension', 12)->index();
            $table->string('mime', 127)->index();
            $table->integer('size')->unsigned();
            $table->boolean('public')->default(false)->index();
            $table->boolean('hidden')->default(false)->index();
            $table->text('metadata');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('files');
    }
}
