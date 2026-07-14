@if(filled($date ?? null))
    <div @class(['actualization', 'actualization--compact' => ($compact ?? false)])>
        Информация актуализирована {{ $date }}
    </div>
@endif
