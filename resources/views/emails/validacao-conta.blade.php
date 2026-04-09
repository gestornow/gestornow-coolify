<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <title>Validação de Conta - GestorNow</title>
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
            color: white;
            background: #efefef;
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
        
        .brand-text {
            font-size: 24px;
            font-weight: 700;
            margin-top: 8px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
        
        /* Botão CTA */
        .cta {
            text-align: center;
            margin: 32px 0;
        }
        
        .btn-primary {
            display: inline-block;
            background: linear-gradient(135deg, #0397f9, #04a4ff);
            color: white;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 8px 24px rgba(3, 151, 249, 0.3);
            transition: transform 0.2s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(3, 151, 249, 0.4);
        }
        
        /* Alerta */
        .alert-note {
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
            color: #856404;
            font-size: 14px;
            text-align: center;
        }
        
        /* Link Box */
        .link-box {
            background: #f8fafc;
            border: 2px solid #e5e9f2;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 14px;
            color: #64748b;
            text-align: center;
        }
        
        /* Divider */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e5e9f2, transparent);
            margin: 32px 0 24px;
        }
        
        /* Steps */
        h3 {
            color: #2d2e32;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .list-steps {
            list-style: none;
            counter-reset: step;
            padding: 0;
        }
        
        .list-steps li {
            position: relative;
            padding: 16px 0 16px 60px;
            margin-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .list-steps li:last-child {
            border-bottom: none;
        }
        
        .list-steps li:before {
            counter-increment: step;
            content: counter(step);
            position: absolute;
            left: 0;
            top: 16px;
            width: 36px;
            height: 36px;
            background: #f3f3f3;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(3, 151, 249, 0.3);
        }
        
        .list-steps strong {
            color: #2d2e32;
            font-weight: 600;
        }
        
        .muted {
            color: #64748b;
            font-size: 14px;
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
            .btn-primary { padding: 14px 28px; font-size: 15px; }
            .list-steps li { padding: 14px 0 14px 50px; }
            .list-steps li:before { width: 32px; height: 32px; top: 14px; font-size: 13px; }
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
            <p style="text-align: center;">Você iniciou o cadastro da empresa <strong>{{ $razao_social }}</strong> na plataforma GestorNow.</p>
            <p>Para concluir e definir sua senha de acesso, valide seu endereço de email clicando no botão abaixo:</p>

            <div class="cta">
                <a href="{{ $link }}" style="color:white;" class="btn-primary" target="_blank" rel="noopener">
                    Continuar
                </a>
            </div>

            <div class="alert-note">
                 <strong>Importante:</strong> Este link expira em <strong>24 horas</strong>. Após o prazo, será necessário iniciar novamente o processo de cadastro.
            </div>

            <p>Se o botão não funcionar, copie e cole esta URL no seu navegador:</p>
            <div class="link-box">{{ $link }}</div>

            <p class="muted" style="margin-top:28px; text-align: center;">
                Não solicitou este cadastro? Ignore este email e nenhuma ação será tomada.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>&copy; {{ date('Y') }} GestorNow. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>