@extends('laravel-database-backup::mail.layout')

@section('title', 'Database backup succeeded')

@section('preview', 'A new backup of the database is in your backup bucket.')

@section('content')
  <p style="margin: 0 0 16px;">
    <span style="display: inline-block; border-radius: 9999px; background-color: #d9f1d1; padding: 4px 12px; font-size: 12px; font-weight: 600; line-height: 20px; text-transform: uppercase; color: #337046;">{{ $environment }}</span>
  </p>

  <h1 style="margin: 0 0 24px; font-size: 24px; font-weight: 600; line-height: 32px; color: #292928;">✅ Database backup succeeded</h1>

  <p style="margin: 0; line-height: 24px;">A new backup of the {{ $appName }} database is stored in your backup bucket.</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; margin-top: 24px; border-radius: 12px; background-color: #f7f6f3;">
    <tr>
      <td style="padding: 8px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%;">
          @include('laravel-database-backup::mail.partials.row', ['label' => 'File', 'value' => $path])
          @include('laravel-database-backup::mail.partials.row', ['label' => 'Size', 'value' => $size])
          @include('laravel-database-backup::mail.partials.row', ['label' => 'Duration', 'value' => $duration])
          @include('laravel-database-backup::mail.partials.row', ['label' => 'Environment', 'value' => $environment])
          @include('laravel-database-backup::mail.partials.row', ['label' => 'Date', 'value' => $date, 'last' => true])
        </table>
      </td>
    </tr>
  </table>
@endsection
