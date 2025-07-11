<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PhoneNumberService
{
    /**
     * Formata o número de telefone para o padrão +55 (ex: +5512988...).
     *
     * @param string|null $number Número de telefone a ser formatado
     * @return string Número formatado ou 'Não fornecido' se inválido
     */
    public function formatPhoneNumber(?string $number): string
    {
        if (preg_match('/^\+55\d{10,11}$/', $number)) {
            Log::info("Número já está no padrão: {$number}");
            return $number;
        }

        if (empty($number) || $number === 'Não fornecido') {
            Log::info("Número não fornecido ou vazio: {$number}");
            return 'Não fornecido';
        }

        $cleanNumber = preg_replace('/[^0-9]/', '', $number);
        $length = strlen($cleanNumber);

        if (substr($cleanNumber, 0, 1) === '0') {
            $cleanNumber = substr($cleanNumber, 1);
            $length = strlen($cleanNumber);
        }

        $validDDDs = [
            11, 12, 13, 14, 15, 16, 17, 18, 19,
            21, 22, 24, 27, 28,
            31, 32, 33, 34, 35, 37, 38,
            41, 42, 43, 44, 45, 46, 47, 48, 49,
            51, 53, 54, 55,
            61, 62, 63, 64, 65, 66, 67, 68, 69,
            71, 73, 74, 75, 77, 79,
            81, 82, 83, 84, 85, 86, 87, 88, 89,
            91, 92, 93, 94, 95, 96, 97, 98, 99
        ];

        if ($length < 10 || $length > 11) {
            Log::warning("Número inválido, comprimento incorreto: {$number} (limpo: {$cleanNumber}, {$length} dígitos)");
            return 'Não fornecido';
        }

        $ddd = intval(substr($cleanNumber, 0, 2));
        if (!in_array($ddd, $validDDDs)) {
            Log::warning("DDD inválido: {$ddd}");
            return 'Não fornecido';
        }

        $formattedNumber = '+55' . $cleanNumber;
        Log::info("Número formatado com sucesso: {$number} -> {$formattedNumber}");
        return $formattedNumber;
    }
}
