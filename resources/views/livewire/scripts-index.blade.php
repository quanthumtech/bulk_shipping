<div>
    <x-mary-header title="Scripts" subtitle="Gerencie os Scripts Webhook/Api's" separator />

    <div class="mt-6">
        <!-- Webhook SyncFlow -->
        <x-mary-card title="Modelo de JSON para Webhook SyncFlow">
            <p class="mb-4">Use o seguinte payload JSON para testar o webhook <code>/webhook-bulkship-syncflow</code>. Certifique-se de incluir o cabeçalho <code>Content-Type: application/json</code> e, se necessário, um <code>User-Agent</code> personalizado (e.g., <code>SyncFlow-Webhook</code>). O retorno esperado de uma requisição bem-sucedida é: <code>Webhook SyncFlow received successfully</code>.</p>



            <div class="relative">
                <pre class="bg-gray-800 text-white p-4 rounded-md overflow-x-auto"><code>{
    "contact_name": "João Silva",
    "contact_number": "+5512988878443",
    "contact_number_empresa": "+5511987654321",
    "contact_email": "joao.silva@gmail.com",
    "estagio": "LEADS NOVOS",
    "situacao_contato": "Tentativa de Contato",
    "chatwoot_accoumts": 4,
    "cadencia_id": 1,
    "msg_content": "Olá João, como vai? Recebemos o seu contato. Para confirmar, seu email é joao.silva@gmail.com?"
}</code></pre>
                <x-mary-button
                    label="Copiar JSON"
                    class="absolute top-2 right-2"
                    onclick="navigator.clipboard.writeText(document.querySelector('#syncflow-json').innerText).then(() => alert('JSON copiado!'))"
                />
                <script id="syncflow-json" type="application/json">
{
    "contact_name": "João Silva",
    "contact_number": "+5512988878443",
    "contact_number_empresa": "+5511987654321",
    "contact_email": "joao.silva@gmail.com",
    "estagio": "LEADS NOVOS",
    "situacao_contato": "Tentativa de Contato",
    "chatwoot_accoumts": 4,
    "cadencia_id": 1,
    "msg_content": "Olá João, como vai? Recebemos o seu contato. Para confirmar, seu email é joao.silva@gmail.com?"
}
                </script>
            </div>
        </x-mary-card>

        <!-- Webhook Zoho -->
        <x-mary-card title="Modelo de JSON para Webhook Zoho" class="mt-6">
            <p class="mb-4">Use o seguinte payload JSON para testar o webhook <code>/webhook-bulkship</code>. Certifique-se de incluir o cabeçalho <code>Content-Type: application/json</code> e, se necessário, um <code>User-Agent</code> personalizado (e.g., <code>Zoho-Webhook</code>) ou uma chave de API específica do Zoho CRM. O retorno esperado de uma requisição bem-sucedida é: <code>Webhook received successfully</code>.</p>

            <div class="relative">
                <pre class="bg-gray-800 text-white p-4 rounded-md overflow-x-auto"><code>{
    "id_card": "980980980",
    "contact_name": "João Silva",
    "contact_number": "+5512988878443",
    "contact_number_empresa": "+5511987654321",
    "contact_email": "joao.silva@gmail.com",
    "estagio": "LEADS NOVOS",
    "situacao_contato": "Tentativa de Contato",
    "chatwoot_accoumts": 4,
    "id_vendedor": "5697065000023257001",
    "cadencia_id": 1,
    "msg_content": "Olá João, como vai? Recebemos o seu contato. Para confirmar, seu email é joao.silva@gmail.com?"
}</code></pre>
                <x-mary-button
                    label="Copiar JSON"
                    class="absolute top-2 right-2"
                    onclick="navigator.clipboard.writeText(document.querySelector('#zoho-json').innerText).then(() => alert('JSON copiado!'))"
                />
                <script id="zoho-json" type="application/json">
{
    "id_card": "980980980",
    "contact_name": "João Silva",
    "contact_number": "+5512988878443",
    "contact_number_empresa": "+5511987654321",
    "contact_email": "joao.silva@gmail.com",
    "estagio": "LEADS NOVOS",
    "situacao_contato": "Tentativa de Contato",
    "chatwoot_accoumts": 4,
    "id_vendedor": "5697065000023257001",
    "cadencia_id": 1,
    "msg_content": "Olá João, como vai? Recebemos o seu contato. Para confirmar, seu email é joao.silva@gmail.com?"
}
                </script>
            </script>
            </div>
        </x-mary-card>
    </div>
</div>
