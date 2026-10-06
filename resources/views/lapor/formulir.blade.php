@extends('layouts.publik')
@section('judul', 'Laporkan penyalahgunaan')
@section('isi')
    <h1>Laporkan tautan bermasalah</h1>
    <p class="bantu">Gunakan formulir ini bila tautan Alias FKIP mengarah ke penipuan, malware, judi daring, atau konten yang tidak pantas. Laporan ditinjau admin dalam satu hari kerja.</p>

    @if (session('terkirim'))
        <p class="info" role="status">Terima kasih. Laporan Anda sudah kami terima dan akan ditinjau.</p>
    @else
        <form method="POST" action="{{ route('lapor.kirim') }}" novalidate>
            @csrf
            <div class="sembunyi" aria-hidden="true">
                <label for="website">Jangan diisi</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <label for="kode">Kode atau URL tautan pendek</label>
            <input id="kode" name="kode" value="{{ old('kode', $kode) }}" required maxlength="255" placeholder="mis. Abc1234 atau https://.../Abc1234">
            @error('kode')<div class="galat" role="alert">{{ $message }}</div>@enderror

            <label for="kategori">Jenis masalah</label>
            <select id="kategori" name="kategori" required>
                <option value="">— Pilih —</option>
                @foreach ($kategori as $k)
                    <option value="{{ $k->value }}" @selected(old('kategori') === $k->value)>{{ $k->getLabel() }}</option>
                @endforeach
            </select>
            @error('kategori')<div class="galat" role="alert">{{ $message }}</div>@enderror

            <label for="keterangan">Keterangan <span class="bantu">(opsional)</span></label>
            <textarea id="keterangan" name="keterangan" rows="4" maxlength="2000">{{ old('keterangan') }}</textarea>
            @error('keterangan')<div class="galat" role="alert">{{ $message }}</div>@enderror

            <label for="email">Surel Anda <span class="bantu">(opsional, untuk tindak lanjut)</span></label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="255">
            @error('email')<div class="galat" role="alert">{{ $message }}</div>@enderror

            <button type="submit">Kirim laporan</button>
        </form>
    @endif
@endsection
