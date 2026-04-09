<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <title>Código de Redefinição de Senha - GestorNow</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <style>
        /* Reset e Base */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #2d2e32;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px 0;
        }
        
        /* Layout Principal */
        .wrapper {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        /* Header com Logo */
        .email-header {
            padding: 40px 30px;
            text-align: center;
            color:#efefefradient(135deg, #0397f9, #04a4ff);
        }
        
        .email-header span {
            height: 60px !important;
            max-height: 60px;
            display: inline-block;
        }
        
        .email-header span img {
            height: 60px !important;
            max-height: 60px;
            width: auto;
        }
        
        /* Conteúdo Principal */
        .email-content {
            padding: 40px 30px;
        }
        
        h2 {
            color: #2d2e32;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 16px;
            text-align: center;
        }
        
        p {
            color: #6b6f7b;
            font-size: 16px;
            margin-bottom: 16px;
            line-height: 1.6;
        }
        
        /* Código */
        .code-container {
            background: linear-gradient(135deg, #0397f9, #04a4ff);
            border-radius: 16px;
            padding: 32px;
            margin: 32px 0;
            text-align: center;
            box-shadow: 0 8px 24px rgba(3, 151, 249, 0.3);
        }
        
        .code-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .code-value {
            color: white;
            font-size: 48px;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            letter-spacing: 8px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            margin-bottom: 8px;
        }
        
        .code-subtitle {
            color: rgba(255, 255, 255, 0.8);
            foAlerta */
        .alert-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
            color: #856404;
            font-size: 14px;
            text-align: center;
        }
        
        /* Instruções */
        .instructions {
            background: #f8fafc;
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
        }
        
        .instructions h3 {
            color: #2d2e32;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .instructions ol {
            padding-left: 20px;
            margin: 0;
        }
        
        .instructions li {
            color: #6b6f7b;
            margin-bottom: 8px;
            font-size: 15px;
        }
        
        .instructions strong {
            color: #2d2e32;
            font-weight: 600;
        }
        
        /* Footer */
        .footer {
            background: #f8fafc;
            padding: 24px 30px;
            text-align: center;
            border-top: 1px solid #e5e9f2;
            margin-top: 32px;
        }
        
        .footer p {
            margin-bottom: 8px;
            font-size: 14px;
            color: #64748b;
        }
        
        .footer a {
            color: #0397f9;
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        /* Responsivo */
        @media (max-width: 600px) {
            body { padding: 10px 0; }
            .wrapper { margin: 0 10px; border-radius: 12px; }
            .email-header { padding: 30px 20px; }
            .email-content { padding: 30px 20px; }
            .footer { padding: 20px; }
            h2 { font-size: 24px; }
            .code-value { font-size: 36px; letter-spacing: 4px; }
            .code-container { padding: 24px; }
            .instructions { padding: 20px; }
            .email-header span img {
                height: 60px !important;
                max-height: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Header com Logo -->
        <div class="email-header">
            <a href="{{url('/')}}" class="d-inline-flex align-items-center justify-content-center">
                <span style="height: 60px;">
                    <div>
                        <img src="{{ asset('assets/img/gestor_now_transparent.png') }}" alt="GestorNow Logo" style="height: 60px;">
                    </div>
                </span>
            </a>
        </div>
        
        <!-- Conteúdo Principal -->
        <div class="email-content">
            <h2>Olá, {{ $nome }}!</h2>
            <p style="text-align: center;">Você solicitou a redefinição de sua senha na plataforma GestorNow.</p>
            <p>Use o código abaixo para continuar o processo de redefinição:</p>

            <div class="code-container">
                <div class="code-label">Seu Código de Verificação</div>
             <div class="code-value">{{ $codigo }}</div>
                <p class="code-subtitle">Este código é válido por 15 minutos</p>
            </div>

            <div class="alert-note">
                ⚠️ <strong>Importante:</strong> Este código expira em <strong>15 minutos</strong>. Se não utilizá-lo dentro deste prazo, será necessário solicitar um novo código.
            </div>

            <div class="instructions">
                <h3>Como usar o código:</h3>
                <ol>
                    <li><strong>Acesse a página de redefinição</strong><br>Volte para a página onde solicitou o código</li>
                    <li><strong>Digite o código de 6 dígitos</strong><br>Insira exatamente: <strong>{{ $codigo }}</strong></li>
                    <li><strong>Crie sua nova senha</strong><br>Escolha uma senha forte com pelo menos 8 caracteres</li>
                    <li><strong>Confirme a alteração</strong><bra senha será atualizada e você poderá fazer login</li>
                </ol>
            </div>

            <p class="muted" style="margin-top:28px; text-align: center; color: #64748b; font-size: 14px;">
                🔒 Não solicitou esta redefinição? Ignore este email e sua senha permanecerá inalterada.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>&copy; {{ date('Y') }} GestorNow. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>