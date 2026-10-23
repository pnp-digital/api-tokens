<?php

namespace Pipeandpiper\Mail\Traits;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Pipeandpiper\Mail\Exceptions\MailTemplateException;
use Pipeandpiper\Mail\Mail\GenericMailable;
use Symfony\Component\Finder\Finder;

trait MailTemplateTrait
{
    /**
     * @const How long to cache the mailable array which maps the slug names to class names
     */
    private const MAILABLE_CACHE_TTL_SECS = 3600;

    /**
     * Get the blade content plus list of variable names required to fill a template,
     * given the slug name.
     *
     * @param string $slug slug name to retrieve
     * @return array an array containing the template and replacement variables
     * @throws MailTemplateException on failure to parse or locate template
     */
    private function getEmailTemplateAsArray(string $slug): array
    {
        $mailableTypes = $this->getMailables();
        if (!array_key_exists($slug, $mailableTypes)) {
            throw new MailTemplateException('No such template. Choices are: ' . json_encode($mailableTypes));
        }
        $mailable = new $mailableTypes[$slug]('', []);
        return [
            'slug' => $slug,
            'mailable' =>  $mailable::class,
            'replacements' => $mailable::VARIABLES,
        ];
    }

    /**
     * API endpoint to send an email given its slug name and a list of replacement variables.
     * Replacement variables and slug name are validated before calling any blade view rendering on it.
     *
     * @param string $toAddress
     * @param array|null $ccAddresses
     * @param string $slug email template slug name
     * @param array $replacements
     * @return ?string error message on failure, null on success
     */
    public function sendEmail(string $toAddress, ?array $ccAddresses, string $slug, array $replacements): ?string
    {
        try {
            /* Validate the passed in replacement variable names against the template */
            $templateVars = $this->getEmailTemplateAsArray($slug);

            $missingFromInput = array_diff($templateVars['replacements'], array_keys($replacements));
            $missingFromTemplate = array_diff(array_keys($replacements), $templateVars['replacements']);

            if (count($missingFromInput) > 0) {
                throw new MailTemplateException(
                    sprintf("Replacement variables [%s] are not defined in your JSON", join(',', $missingFromInput))
                );
            } elseif (count($missingFromTemplate) > 0) {
                throw new MailTemplateException(
                    sprintf("Replacement variables [%s] are not defined in the template", join(',', $missingFromTemplate))
                );
            }
        }
        catch (MailTemplateException $e) {
            $error = sprintf(
                "Email template %s failed to send to %s: [%s]: %s",
                $slug,
                $toAddress,
                json_encode($replacements),
                $e->getMessage()
            );
            Log::channel('mailables')->info($error);
            return $error;
        }

        $mailableTypes = $this->getMailables();
        $mailable = new $mailableTypes[$slug]($toAddress, $replacements);

        /* Now we are pretty sure we have the right variables to render this blade template, do it */
        if (!empty($ccAddresses)) {
            $sent = Mail::to($toAddress)->cc($ccAddresses)->send($mailable);
        } else {
            $sent = Mail::to($toAddress)->send($mailable);
        }
        if ($sent === null) {
            $error = sprintf("Email template %s failed to send to %s: %s", $slug, $toAddress, json_encode($replacements));
            Log::channel('mailables')->info($error);
            return $error;
        }

        Log::channel('mailables')->info(
            sprintf(
                "Email template %s sent to %s: %s",
                $slug,
                $toAddress,
                json_encode($replacements)
            )
        );
        return null;
    }

    /**
     * Return an array mapping GenericMailable derived classes in the App\Mail namespace to template names.
     *
     * @return array
     */
    public function getMailables(): array
    {
        return Cache::remember("mailables", static::MAILABLE_CACHE_TTL_SECS, function () {
            $mailableTypes = [];
            $finderFiles = Finder::create()->files()->in(app_path() . '/Mail')->name('*.php');
            foreach ($finderFiles as $finderFile) {
                $class = '\\App\\Mail\\' . $finderFile->getFilenameWithoutExtension();
                if (is_subclass_of($class, GenericMailable::class)) {
                    $mailableTypes[$class::getEmailView()] = $class;
                }
            }
            return $mailableTypes;
        });
    }
}