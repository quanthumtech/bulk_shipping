<?php

namespace Tests\App\Http\Controllers;

use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\CadenceMessage;
use App\Models\Etapas;
use App\Models\SyncFlowLeads;
use App\Models\User;
use App\Services\ChatwootService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

Class a_WebhookZohoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sobrescreve o ChatwootService
        $this->app->bind(ChatwootService::class, function () {
            return new class extends ChatwootService {
                public function isWhatsappNumber($number)
                {
                    return $number === '123456789';
                }

                public function sendMessage($number = null, $message = null, $apiPost = null, $apiKey = null)
                {
                    Log::info("Mensagem simulada enviada: $message para $number");
                }
            };
        });

        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('error')->byDefault();
    }

    #[Test]
    public function it_creates_a_new_lead_from_webhook()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'chatwoot_accoumts' => '123456',
            'api_post' => 'api_post_value',
            'apikey' => 'apikey_value',
            'type_user' => 'default_type',
            'token_acess' => 'token_acess_value',
        ]);

        $response = $this->postJson('/api/webhook-bulkship', [
            'id_card' => '123456',
            'contact_name' => 'John Doe',
            'contact_number' => '123456789',
            'contact_number_empresa' => '987654321',
            'contact_email' => 'test@example.com',
            'estagio' => 'Estágio 1',
            'chatwoot_accoumts' => '123456',
            'id_cadencia' => '1',
            'situacao_contato' => 'Contato Efetivo',
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook received successfully');

        $this->assertDatabaseHas('sync_flow_leads', [
            'id_card' => '123456',
            'contact_name' => 'John Doe',
            'contact_number' => '123456789',
        ]);
    }

    #[Test]
    public function it_updates_an_existing_lead_from_webhook()
    {
        $existingLead = SyncFlowLeads::create([
            'id_card' => '123456',
            'contact_name' => 'Old Name',
            'contact_number' => '111111111',
            'contact_number_empresa' => '111111111',
            'contact_email' => 'old@example.com',
            'estagio' => 'Old Stage',
            'chatwoot_accoumts' => '123456',
            'situacao_contato' => 'Old Status',
            'token_acess' => 'token_acess_value',
            'cadencia_id' => 1,
        ]);

        $response = $this->postJson('/api/webhook-bulkship', [
            'id_card' => '123456',
            'contact_name' => 'John Doe',
            'contact_number' => '123456789',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sync_flow_leads', [
            'id_card' => '123456',
            'contact_name' => 'John Doe',
            'contact_number' => '123456789',
        ]);
    }

    #[Test]
    public function it_sends_immediate_cadence_message_if_applicable()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'chatwoot_accoumts' => '123456',
            'api_post' => 'api_post_value',
            'apikey' => 'apikey_value',
            'type_user' => 'default_type',
            'token_acess' => 'token_acess_value',
        ]);

        // Cria uma cadência para satisfazer a chave estrangeira
        DB::table('cadencias')->insert([
            'id' => 1,
            'name' => 'Test Cadence',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $etapa = Etapas::create([
            'titulo' => 'Etapa 1',
            'cadencia_id' => 1,
            'imediat' => 1,
            'active' => 1,
            'message_content' => 'Mensagem da etapa imediata',
        ]);

        // Cria o lead antes da requisição
        $lead = SyncFlowLeads::create([
            'id_card' => '123456',
            'contact_name' => 'John Doe',
            'contact_number' => '123456789',
            'contact_number_empresa' => '987654321',
            'contact_email' => 'test@example.com',
            'estagio' => 'Estágio 1',
            'chatwoot_accoumts' => '123456',
            'cadencia_id' => 1,
            'situacao_contato' => 'Contato Efetivo',
            'token_acess' => 'token_acess_value',
        ]);

        $response = $this->postJson('/api/webhook-bulkship', [
            'id_card' => '123456',
            'contact_number' => '123456789',
            'chatwoot_accoumts' => '123456',
            'id_cadencia' => '1',
        ]);

       // Assert
       $response->assertStatus(200);
       $this->assertDatabaseHas('cadence_messages', [
           'sync_flow_leads_id' => $lead->id, // Usa o ID dinâmico do lead criado
           'etapa_id' => $etapa->id,
       ]);
    }

    #[Test]
    public function it_returns_400_if_no_data_is_received()
    {
        // Act
        $response = $this->post('/api/webhook-bulkship', []);

        // Assert
        $response->assertStatus(400);
        $response->assertSee('No data received');
    }
}
