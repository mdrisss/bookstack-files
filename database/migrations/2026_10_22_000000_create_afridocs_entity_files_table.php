<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afridocs_entity_files', function (Blueprint $table) {
            $table->increments('id');
            $table->string('entity_type', 20);
            $table->unsignedInteger('entity_id');
            $table->string('name');
            $table->string('path', 400);
            $table->string('extension', 20)->default('');
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afridocs_entity_files');
    }
};
