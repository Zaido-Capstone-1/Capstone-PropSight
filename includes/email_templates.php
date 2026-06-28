<?php
/**
 * Shared email template helper for refund notifications.
 * Usage: $html = refund_email_html('received' | 'processing' | 'completed' | 'declined', $data);
 */

function refund_email_html(string $type, array $d): string
{
    $styles = [
        'received' => ['bg' => '#1e40af', 'icon' => '&#128338;', 'title' => 'Refund request received', 'badge_bg' => '#eff6ff', 'badge_color' => '#1e40af', 'badge_label' => 'Pending review'],
        'processing' => ['bg' => '#047857', 'icon' => '&#10003;', 'title' => 'Refund approved', 'badge_bg' => '#ecfdf5', 'badge_color' => '#047857', 'badge_label' => 'Processing'],
        'completed' => ['bg' => '#15803d', 'icon' => '&#10003;', 'title' => 'Refund completed', 'badge_bg' => '#f0fdf4', 'badge_color' => '#15803d', 'badge_label' => 'Completed'],
        'declined' => ['bg' => '#b91c1c', 'icon' => '&#10007;', 'title' => 'Refund request declined', 'badge_bg' => '#fef2f2', 'badge_color' => '#b91c1c', 'badge_label' => 'Declined'],
    ];
    $s = $styles[$type] ?? $styles['received'];
    $name = htmlspecialchars($d['name'] ?? '');
    $ref = htmlspecialchars($d['ref'] ?? '');
    $refLabel = htmlspecialchars($d['ref_label'] ?? 'Reference');
    $amt = htmlspecialchars($d['amount'] ?? '');
    $method = htmlspecialchars($d['method'] ?? '');
    $date = htmlspecialchars($d['date'] ?? '');
    $reason = htmlspecialchars($d['reason'] ?? '');
    $declineRsn = htmlspecialchars($d['decline_reason'] ?? '');

    $sep = "border-bottom:0.5px solid #e5e7eb;";
    $td1 = "padding:9px 16px;color:#6b7280;font-size:13px;{$sep}";
    $td2 = "padding:9px 16px;font-weight:500;color:#111827;text-align:right;font-size:13px;{$sep}";

    // Build rows
    $rows = "
        <tr>
            <td style='{$td1}'>{$refLabel}</td>
            <td style='{$td2}'>{$ref}</td>
        </tr>
        <tr>
            <td style='{$td1}'>Refund amount</td>
            <td style='padding:9px 16px;font-weight:500;color:{$s['bg']};text-align:right;font-size:13px;{$sep}'>{$amt}</td>
        </tr>";

    if ($method) {
        $rows .= "
        <tr>
            <td style='{$td1}'>Payment method</td>
            <td style='{$td2}'>{$method}</td>
        </tr>";
    }

    if ($date) {
        $dateLabel = $type === 'completed' ? 'Completed on' : 'Payment date';
        $rows .= "
        <tr>
            <td style='{$td1}'>{$dateLabel}</td>
            <td style='{$td2}'>{$date}</td>
        </tr>";
    }

    if ($reason && $type === 'received') {
        $rows .= "
        <tr>
            <td style='{$td1}'>Your reason</td>
            <td style='{$td2}'>{$reason}</td>
        </tr>";
    }

    if ($declineRsn && $type === 'declined') {
        $rows .= "
        <tr>
            <td style='{$td1}'>Reason for decline</td>
            <td style='padding:9px 16px;font-weight:500;color:#b91c1c;text-align:right;font-size:13px;{$sep}'>{$declineRsn}</td>
        </tr>";
    }

    // Status row (last — no border)
    $rows .= "
        <tr>
            <td style='padding:9px 16px;color:#6b7280;font-size:13px;'>Status</td>
            <td style='padding:9px 16px;text-align:right;font-size:13px;'>
                <span style='background:{$s['badge_bg']};color:{$s['badge_color']};padding:3px 10px;border-radius:4px;font-size:12px;font-weight:500;'>{$s['badge_label']}</span>
            </td>
        </tr>";

    $bodies = [
        'received' => "We've received your refund request and it's now under review. Our team will get back to you within 1&#8211;2 business days.",
        'processing' => "Your refund has been approved. We're now processing the return &#8212; please allow 3&#8211;5 business days for the amount to reflect on your original payment method.",
        'completed' => "Your refund has been completed. The amount has been returned to your " . ($method ?: 'original payment method') . ".",
        'declined' => "After reviewing your request, we weren't able to approve this refund. The reason is noted below.",
    ];
    $body = $bodies[$type] ?? '';

    $footers = [
        'received' => "Refund requests are accepted within 30 days of payment. One request is allowed per invoice. Questions? Contact our support team and reference <strong>{$ref}</strong>.",
        'processing' => "You'll receive another email once the refund is completed. For questions, contact our support team and reference <strong>{$ref}</strong>.",
        'completed' => "If you haven't received the refund after 5 business days, please contact our support team and reference <strong>{$ref}</strong>.",
        'declined' => "If you believe this is an error, contact our support team and reference <strong>{$ref}</strong>.",
    ];
    $footer = $footers[$type] ?? '';

    return "
    <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f3f4f6;padding:32px 16px;'>
        <div style='background:#fff;border-radius:10px;overflow:hidden;'>
            <div style='background:{$s['bg']};padding:22px 28px;'>
                <h1 style='color:#fff;margin:0;font-size:19px;font-weight:700;'>{$s['icon']}&nbsp;{$s['title']}</h1>
            </div>
            <div style='padding:24px 28px;'>
                <p style='color:#374151;font-size:14px;line-height:1.6;margin:0 0 16px;'>Hi {$name},</p>
                <p style='color:#374151;font-size:14px;line-height:1.6;margin:0 0 20px;'>{$body}</p>
                <table style='width:100%;border-collapse:collapse;background:#f8fafc;border-radius:8px;border:0.5px solid #e5e7eb;margin-bottom:20px;'>
                    {$rows}
                </table>
                <p style='font-size:12px;color:#9ca3af;margin:0;line-height:1.6;border-top:0.5px solid #e5e7eb;padding-top:16px;'>{$footer}</p>
            </div>
        </div>
    </div>";
}