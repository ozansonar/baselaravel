{{--
    Sunucu hatası sayfası ortak parçada: çerçeve kendi errors/500 görünümünü
    de taşıyor ve uygulamada dosya yoksa o devreye giriyor — yani 5xx yedeğine
    hiç sıra gelmiyor. Yaygın kodların her biri kendi dosyasıyla duruyor.
--}}
@include('errors._server-error')
