@extends('laravel-database-backup::mail.layout')

@section('title', 'Database backup failed')

@section('preview', 'The last database backup did not finish.')

@section('content')
  <p style="margin: 0 0 16px;">
    <span style="display: inline-block; border-radius: 9999px; background-color: #ffdbd6; padding: 4px 12px; font-size: 12px; font-weight: 600; line-height: 20px; text-transform: uppercase; color: #7f0f00;">{{ $environment }}</span>
  </p>

  <h1 style="margin: 0 0 24px; font-size: 24px; font-weight: 600; line-height: 32px; color: #292928;">❌ Database backup failed</h1>

  <p style="margin: 0; line-height: 24px;">The last backup of the {{ $appName }} database did not finish.</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; margin-top: 24px; border-radius: 12px; background-color: #f7f6f3;">
    <tr>
      <td style="padding: 8px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%;">
          @include('laravel-database-backup::mail.partials.row', ['label' => 'Environment', 'value' => $environment])
          @include('laravel-database-backup::mail.partials.row', ['label' => 'Date', 'value' => $date, 'last' => true])
        </table>
      </td>
    </tr>
  </table>

  <p style="margin: 24px 0 8px; font-size: 14px; font-weight: 600; color: #7f0f00;">Error</p>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%;">
    <tr>
      <td dir="ltr" align="left" style="border: 1px solid #ffdbd6; border-radius: 12px; background-color: #f7f6f3; padding: 16px; font-family: Menlo, Consolas, monospace; font-size: 12px; line-height: 20px; color: #292928; word-break: break-all; white-space: pre-wrap;">{{ $error }}</td>
    </tr>
  </table>

  <p style="margin: 24px 0 0; font-size: 14px; line-height: 24px; color: #5a5a59;">Fix the error above, then run <code>php artisan backup:database</code> to try again.</p>
@endsection
