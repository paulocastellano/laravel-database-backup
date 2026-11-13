@php($border = ($last ?? false) ? '' : 'border-bottom: 1px solid #eae8e5;')
<tr>
  <td align="left" valign="top" style="padding: 12px 16px 12px 0; font-size: 14px; line-height: 20px; color: #5a5a59; white-space: nowrap; {{ $border }}">{{ $label }}</td>
  <td align="right" valign="top" dir="ltr" style="padding: 12px 0; font-size: 14px; line-height: 20px; font-weight: 600; color: #292928; word-break: break-all; {{ $border }}">{{ $value }}</td>
</tr>
