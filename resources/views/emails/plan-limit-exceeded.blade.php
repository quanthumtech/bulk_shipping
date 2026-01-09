<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Limite de Plano Excedido</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; }
        .content { padding: 20px; }
        .button { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Olá, {{ $user->name }}!</h1>
    </div>
    
    <div class="content">
        <p>Seu plano atual não permite receber mais leads hoje. <strong>Razão:</strong> {{ $reason }}</p>
        
        <p>Para continuar recebendo leads ilimitados, acesse o painel e considere fazer upgrade para um plano superior.</p>
        
        <p><a href="{{ route('users.config') }}" class="button">Ver Planos</a></p>
        
        <p>Se precisar de ajuda, entre em contato com o suporte.</p>
    </div>
    
    <div class="footer">
        <p>Atenciosamente,<br>Equipe Plataforma Mundo</p>
    </div>
</body>
</html>