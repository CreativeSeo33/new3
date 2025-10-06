<?php

namespace App\Security\Jwt;

use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Ensures cookie-based token is preferred over Authorization header.
 * Falls back to the original chain extractor when cookie is absent.
 */
class CookieFirstChainExtractor implements TokenExtractorInterface
{
    private TokenExtractorInterface $cookieExtractor;
    private TokenExtractorInterface $innerChain;

    public function __construct(
        TokenExtractorInterface $cookieExtractor,
        TokenExtractorInterface $inner
    ) {
        $this->cookieExtractor = $cookieExtractor;
        $this->innerChain = $inner;
    }

    /**
     * @return string|false
     */
    public function extract(Request $request)
    {
        $token = $this->cookieExtractor->extract($request);
        if ($token) {
            return $token;
        }

        return $this->innerChain->extract($request);
    }
}


