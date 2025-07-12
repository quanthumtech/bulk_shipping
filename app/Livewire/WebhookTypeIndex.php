<?php

namespace App\Livewire;

use App\Models\WebhookLog;
use Livewire\Component;

class WebhookTypeIndex extends Component
{
    public function getWebhookTypeOptionsProperty()
    {
        $webhookTypes = WebhookLog::select('webhook_type')
            ->distinct()
            ->whereNotNull('webhook_type')
            ->pluck('webhook_type')
            ->map(function ($type) {
                return [
                    'id' => $type,
                    'name' => ucfirst($type),
                ];
            })
            ->toArray();

        return array_merge(
            [['id' => '', 'name' => 'Todos']],
            $webhookTypes
        );
    }

    public function render()
    {
        return view('livewire.webhook-type-index', [
            'webhookTypeOptions' => $this->webhookTypeOptions,
        ]);
    }
}