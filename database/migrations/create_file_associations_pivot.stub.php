<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFileassociationsPivot extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('file_associations', function (Blueprint $table) {
            $table->integer('file_id')->unsigned()->index();
            $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade');
            $table->integer('association_id')->unsigned()->index();
            $table->string('association_type', 24)->index();
            $table->smallInteger('order')->unsigned()->index();
            $table->string('role', 32)->index();
            $table->mediumText('details');
            $table->primary(['file_id', 'association_id', 'association_type'], 'file_associations');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('file_associations');
    }
}
