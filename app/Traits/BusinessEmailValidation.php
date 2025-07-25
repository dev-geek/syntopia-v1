<?php

namespace App\Traits;

trait BusinessEmailValidation
{
    /**
     * Check if the email is a business email
     * 
     * @param string $email
     * @return bool
     */
    protected function isBusinessEmail($email)
    {
        // Common personal email domains
        $personalDomains = [
            'gmail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com', 'aol.com', 'hotmail.co.uk',
            'hotmail.fr', 'msn.com', 'yahoo.fr', 'wanadoo.fr', 'orange.fr', 'comcast.net',
            'yahoo.co.in', 'yahoo.in', 'yahoo.com.vn', 'live.com', 'rediffmail.com',
            'free.fr', 'gmx.de', 'web.de', 'yandex.ru', 'ymail.com', 'outlook.com',
            'mail.com', 'cox.net', 'hotmail.it', 'sbcglobal.net', 'sfr.fr', 'live.fr',
            'verizon.net', 'me.com', 'gmx.net', 'googlemail.com', 'icloud.com', 'me.com',
            'btinternet.com', 'virginmedia.com', 'blueyonder.co.uk', 'freeserve.co.uk',
            'ntlworld.com', 'o2.co.uk', 'orange.net', 'sky.com', 'talktalk.co.uk', 'tiscali.co.uk',
            'virgin.net', 'bt.com', 'gmx.com', 'zoho.com', 'yandex.com', 'protonmail.com',
            'inbox.com', 'mail.ru', 'list.ru', 'bk.ru', 'mail.ua', 'ukr.net', 'i.ua', 'rambler.ru'
        ];
        
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        
        // Log the original domain
        \Log::info('Original domain extracted', ['email' => $email, 'domain' => $domain]);
        
        // Remove any subdomains 
        $domainParts = explode('.', $domain);
        if (count($domainParts) > 2) {
            $domain = $domainParts[count($domainParts) - 2] . '.' . $domainParts[count($domainParts) - 1];
            \Log::info('Domain after subdomain processing', ['original_domain' => $domain, 'processed_domain' => $domain]);
        }
        
        $isBusiness = !in_array($domain, $personalDomains);
        \Log::info('Business email check result', [
            'email' => $email,
            'domain_checked' => $domain,
            'is_business' => $isBusiness,
            'in_personal_domains' => in_array($domain, $personalDomains)
        ]);
        
        return $isBusiness;
    }
}
