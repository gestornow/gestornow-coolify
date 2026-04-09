<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .content {
            padding: 30px 20px;
        }
        .content h2 {
            color: #667eea;
            font-size: 20px;
            margin-top: 0;
        }
        .content p {
            margin: 15px 0;
            color: #555;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 40px;
            background-color: #667eea;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .button:hover {
            background-color: #764ba2;
        }
        .link-text {
            word-break: break-all;
            background-color: #f5f5f5;
            padding: 12px;
            border-radius: 4px;
            font-size: 12px;
            color: #666;
            margin: 15px 0;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #eee;
        }
        .info-box {
            background-color: #e8f4f8;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box strong {
            color: #667eea;
        }
        ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Defina sua Senha</h1>
        </div>

        <div class="content">
            <h2>Olá {{ $nome }}!</h2>

            <p>Seu cadastro na <strong>{{ $razao_social }}</strong> foi criado com sucesso no <strong>GestorNow</strong>.</p>

            <p>Para finalizar seu registro, é necessário que você defina uma senha segura para sua conta.</p>

            <div class="info-box">
                <strong>⏰ Atenção:</strong> Este link expira em 24 horas. Complete este processo antes do prazo.
            </div>

            <div class="button-container">
                <a href="{{ $link }}" class="button">Criar Minha Senha</a>
            </div>

            <p style="text-align: center; font-size: 14px; color: #999;">Ou copie e cole este link no seu navegador:</p>
            <div class="link-text">{{ $link }}</div>

            <h3 style="color: #667eea; margin-top: 30px; font-size: 16px;">Requisitos para sua senha:</h3>
            <ul>
                <li>Mínimo de 8 caracteres</li>
                <li>Deve conter letras (maiúsculas e/ou minúsculas)</li>
                <li>Deve conter números</li>
                <li>Evite usar informações pessoais óbvias</li>
            </ul>

            <p style="margin-top: 30px; color: #666;">
                Se você não solicitou este cadastro ou não reconhece esta ação, por favor ignore este email.
            </p>

            <p style="color: #999; font-size: 13px;">
                <strong>Dúvidas?</strong> Entre em contato com nosso suporte: <a href="mailto:suporte@gestornow.com" style="color: #667eea;">suporte@gestornow.com</a>
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} GestorNow. Todos os direitos reservados.</p>
            <p>Você recebeu este email porque se registrou em nossa plataforma.</p>
        </div>
    </div>
</body>
</html>
