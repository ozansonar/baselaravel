{{-- Her sayfanın altında: proje adı, belge adı ve sayfa numarası.
     {PAGENO}/{nbpg} mPDF'in kendi sayaçları, sayfalar oluşurken doldurulur. --}}
<table width="100%" class="doc-footer">
    <tr>
        <td>{{ $project }} — {{ $title }}</td>
        <td class="right">{PAGENO} / {nbpg}</td>
    </tr>
</table>
