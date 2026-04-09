@props([
    'selected' => null,
    'name' => 'mes_filtro',
    'showAllOption' => true
])

@php
    $selected = $selected ?? now()->format('Y-m');
    
    // Gerar últimos 12 meses
    $meses = [];
    for ($i = 0; $i < 12; $i++) {
        $data = now()->subMonths($i);
        $meses[] = [
            'value' => $data->format('Y-m'),
            'label' => ucfirst($data->locale('pt_BR')->translatedFormat('F/Y'))
        ];
    }
@endphp

<div class="mb-3">
    <label class="form-label">
        <i class="ti ti-calendar me-1"></i>
        Filtrar por Mês
    </label>
    <select name="{{ $name }}" class="form-select" onchange="this.form.submit()">
        @if($showAllOption)
            <option value="todos" {{ $selected === 'todos' ? 'selected' : '' }}>
                Todos os meses
            </option>
        @endif
        
        @foreach($meses as $mes)
            <option value="{{ $mes['value'] }}" {{ $selected === $mes['value'] ? 'selected' : '' }}>
                {{ $mes['label'] }}
            </option>
        @endforeach
    </select>
</div>
