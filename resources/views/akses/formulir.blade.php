@extends('layouts.publik')
@section('judul', 'Minta akses')
@section('isi')
    <h1>Minta akses Alias FKIP</h1>
    <p class="bantu">Untuk dosen, tendik, dan pengurus unit FKIP. Gunakan surel <strong>@unsil.ac.id</strong>. Kami akan mengirim tautan verifikasi ke surel tersebut.</p>

    @if (session('terkirim'))
        <p class="info" role="status">Jika data Anda memenuhi syarat, surel verifikasi telah dikirim. Periksa kotak masuk (dan folder spam) dalam beberapa menit. Tautan berlaku 24 jam.</p>
    @else
        <form method="POST" action="{{ route('akses.kirim') }}" novalidate>
            @csrf
            <div class="sembunyi" aria-hidden="true">
                <label for="website">Jangan diisi</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <label for="nama">Nama lengkap</label>
            <input id="nama" name="nama" value="{{ old('nama') }}" required maxlength="150">
            @error('nama')<div class="galat" role="alert">{{ $message }}</div>@enderror

            <label for="email">Surel</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="nama@unsil.ac.id">
            @error('email')<div class="galat" role="alert">{{ $message }}</div>@enderror

            <label for="nip">NIP / NIDN <span class="bantu">(opsional)</span></label>
            <input id="nip" name="nip" value="{{ old('nip') }}" maxlength="30">
            @error('nip')<div class="galat" role="alert">{{ $message }}</div>@enderror

            <label for="unit_id">Unit <span class="bantu">(opsional)</span></label>
            <select id="unit_id" name="unit_id">
                <option value="">— Pilih unit —</option>
                @foreach ($unit as $u)
                    <option value="{{ $u->id }}" @selected(old('unit_id') === $u->id)>{{ $u->nama }}</option>
                @endforeach
            </select>
            @error('unit_id')<div class="galat" role="alert">{{ $message }}</div>@enderror

            <label for="alasan">Keperluan</label>
            <textarea id="alasan" name="alasan" rows="4" required maxlength="1000">{{ old('alasan') }}</textarea>
            @error('alasan')<div class="galat" role="alert">{{ $message }}</div>@enderror

            <label><input type="checkbox" name="setuju" value="1" @checked(old('setuju'))> Saya menyetujui <a href="{{ url('/privasi') }}">pemberitahuan privasi</a> Alias FKIP.</label>
            @error('setuju')<div class="galat" role="alert">{{ $message }}</div>@enderror

            <button type="submit">Kirim permintaan</button>
        </form>
    @endif
@endsection
