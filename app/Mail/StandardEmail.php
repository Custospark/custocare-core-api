<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StandardEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $title, $mailBody, $ctaUrl, $ctaLabel, $tip, $logoPath;

    public function __construct($title, $mailBody, $ctaUrl = null, $ctaLabel = null, $tip = null, $logoPath = null)
    {
        $this->title = $title;
        $this->mailBody = $mailBody;
        $this->ctaUrl = $ctaUrl;
        $this->ctaLabel = $ctaLabel;
        $this->tip = $tip;
        $this->logoPath = $logoPath ?: public_path('continuousLogoLight.png'); // Default logo path
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
            'logoPath' => public_path('images/continuousLogoLight.png'),
        ]);

}


}

