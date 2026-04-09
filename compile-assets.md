# Comandos para compilar os assets no servidor

## 1. Primeiro, certifique-se de que as dependências estão instaladas:
```bash
npm install
```

## 2. Compile os assets em modo de desenvolvimento:
```bash
npm run dev
```

## 3. Se quiser compilar para produção (otimizado):
```bash
npm run production
```

## 4. Para assistir mudanças (modo watch):
```bash
npm run watch
```

## Arquivos modificados:

### 1. `resources/views/layouts/sections/scripts.blade.php`
- Adicionado: `<script src="{{ asset(mix('assets/vendor/libs/select2/select2.js')) }}"></script>`

### 2. `resources/views/layouts/sections/styles.blade.php`
- Adicionado: `<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/select2/select2.css')) }}" />`

### 3. `resources/assets/js/select2-global.js`
- Código JavaScript para inicializar o Select2 automaticamente
- Função global `reinitializeSelect2()` para reinicializar quando necessário

## Como usar o Select2:

Simplesmente adicione a classe `select2` ao seu elemento select:

```html
<select class="form-control select2" name="exemplo">
    <option value="">Selecione uma opção</option>
    <option value="1">Opção 1</option>
    <option value="2">Opção 2</option>
</select>
```

O Select2 será inicializado automaticamente!