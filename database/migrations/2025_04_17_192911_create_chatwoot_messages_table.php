<?php

   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   class CreateChatwootMessagesTable extends Migration
   {
       public function up()
       {
           Schema::create('chatwoot_messages', function (Blueprint $table) {
               $table->id();
               $table->unsignedBigInteger('chatwoot_conversation_id');
               $table->text('content')->nullable();
               $table->string('message_id')->unique(); // ID da mensagem no Chatwoot
               $table->timestamps();

               $table->foreign('chatwoot_conversation_id')
                     ->references('id')
                     ->on('chatwoot_conversation')
                     ->onDelete('cascade');
           });
       }

       public function down()
       {
           Schema::dropIfExists('chatwoot_messages');
       }
   }
