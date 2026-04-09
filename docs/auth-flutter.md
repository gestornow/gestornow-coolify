Guia de integração Flutter com a Auth API (GestorNow)

Este documento mostra exemplos de chamadas HTTP para usar os endpoints de autenticação fornecidos por `AuthApiController` e duas abordagens de autenticação: Sanctum (token pessoal) e JWT.

Requisitos
- Backend: Laravel com endpoints em /api/auth conforme documentação OpenAPI (`docs/openapi/auth-api.yaml`).
- Flutter: pacote `http` (ou `dio`) e armazenamento seguro, por exemplo `flutter_secure_storage`.

Instalação (Flutter)
- pubspec.yaml:
  dependencies:
    http: ^0.13.5
    flutter_secure_storage: ^8.0.0

Fluxo comum (Sanctum - token pessoal)
1) Login
- Endpoint: POST /api/auth/login
- Body JSON: { "login": "meu@email.com", "senha": "senha123" }
- Resposta: inclui `api_token` (plainTextToken)
- Aplique: salvar `api_token` em armazenamento seguro e usar como Bearer token nas requisições subsequentes.

Exemplo minimal com `http`:

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final storage = FlutterSecureStorage();
final baseUrl = 'https://api.seuservidor.com/api/auth';

Future<bool> login(String login, String senha) async {
  final resp = await http.post(Uri.parse('
    '
    '
  '));
}
```

(Nota: no exemplo acima substitua pelo seu código real — a versão completa está mais abaixo.)

2) Requisições autenticadas
- Header: Authorization: Bearer <api_token>
- Também é retornado `session_token` para compatibilidade com sessão web; para mobile prefira usar `api_token`.

3) /me
- GET /api/auth/me com Authorization: Bearer <api_token>
- Retorna dados do usuário e empresa.

Logout
- POST /api/auth/logout
- Body: { "session_token": "...", "user_id": 123, "api_token": "<api_token>" }
- Revoga token (se enviado) e invalida sessão.

Exemplo completo em Flutter (usando `http` e `flutter_secure_storage`)

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AuthService {
  final _storage = FlutterSecureStorage();
  final _base = 'https://api.seuservidor.com/api/auth';

  Future<Map<String, dynamic>> login(String login, String senha) async {
    final resp = await http.post(Uri.parse('$_base/login'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'login': login, 'senha': senha}),
    );
    final body = jsonDecode(resp.body);
    if (resp.statusCode == 200 && body['success'] == true) {
      final token = body['data']['api_token'];
      await _storage.write(key: 'api_token', value: token);
      await _storage.write(key: 'session_token', value: body['data']['session_token'] ?? '');
      return {'ok': true, 'data': body['data']};
    }
    return {'ok': false, 'error': body};
  }

  Future<Map<String, dynamic>> me() async {
    final token = await _storage.read(key: 'api_token');
    if (token == null) return {'ok': false, 'error': 'No token'};
    final resp = await http.get(Uri.parse('$_base/me'), headers: {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json'
    });
    final body = jsonDecode(resp.body);
    if (resp.statusCode == 200 && body['success'] == true) {
      return {'ok': true, 'data': body['data']};
    }
    return {'ok': false, 'error': body};
  }

  Future<void> logout(int userId) async {
    final apiToken = await _storage.read(key: 'api_token');
    final sessionToken = await _storage.read(key: 'session_token');
    final resp = await http.post(Uri.parse('$_base/logout'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'user_id': userId,
        'session_token': sessionToken,
        'api_token': apiToken
      }),
    );
    await _storage.delete(key: 'api_token');
    await _storage.delete(key: 'session_token');
  }
}
```

Alternativa: JWT
- Se preferir JWT em vez de Sanctum, eu posso adicionar um endpoint que emite um JWT (com package tymon/jwt-auth ou laravel/passport). O fluxo no Flutter seria o mesmo: salvar token e enviar Authorization: Bearer <jwt>.

Segurança
- Use HTTPS em produção.
- Armazene tokens em `flutter_secure_storage` (não SharedPreferences).
- Implemente refresh token se usar JWT de curta duração.

Documentação YAML
- O arquivo OpenAPI foi criado em `docs/openapi/auth-api.yaml`.

Próximo passo
- Posso adicionar um endpoint que emite JWT e exemplo de validação no backend.
- Posso gerar um cliente OpenAPI (Dart) ou um Postman collection com esses endpoints.

Quer que eu gere também o endpoint JWT no Laravel e códigos completos de exemplo no Flutter (com captura de erros e UI)?