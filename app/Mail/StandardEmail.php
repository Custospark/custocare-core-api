<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StandardEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $mailBody;
    public $ctaUrl;
    public $ctaLabel;
    public $tip;
    public $logoPath;
    public $isHtml;

    public function __construct(
        $title, 
        $mailBody, 
        $ctaUrl = null, 
        $ctaLabel = null, 
        $tip = null, 
        $logoPath = null, 
        $isHtml = true
    ) {
        $this->title = $title;
        $this->mailBody = $mailBody;
        $this->ctaUrl = $ctaUrl;
        $this->ctaLabel = $ctaLabel;
        $this->tip = $tip;
        $this->logoPath = $logoPath ?: public_path('images/continuousLogoLight.png');
        $this->isHtml = $isHtml;
    }

    public function build()
    {
        return $this->subject($this->title)
            ->view('emails.standard')
            ->with([
                'title' => $this->title,
                'mailBody' => $this->mailBody,
                'ctaUrl' => $this->ctaUrl,
                'ctaLabel' => $this->ctaLabel,
                'tip' => $this->tip,
                'logoPath' => $this->logoPath,
                'isHtml' => $this->isHtml,
            ]);
    }
}