{{-- Satırlar veritabanından parça parça çekilir; her parça burada kendi
     HTML'ine dönüşür, sonuç kümesinin tamamı nesne olarak bellekte tutulmaz. --}}
@foreach($rows as $cells)
    <tr>
        @foreach($cells as $cell)
            <td class="{{ $cell['class'] }}">{{ $cell['value'] }}</td>
        @endforeach
    </tr>
@endforeach
