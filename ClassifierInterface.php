<?php

namespace Omnishield;

use Omnishield\Model\Classification;
use Omnishield\Model\Submission;

/**
 * Is this content spam? What a visitor wrote - a comment, a message, a
 * sign-up - and who wrote it, read by a classifier.
 */
interface ClassifierInterface extends GatewayInterface
{
    /**
     * Ham, spam, or spam flagrant enough to drop unseen.
     *
     * @throws Exception\UnreachableException when the provider did not answer
     * @throws Exception\InvalidKeyException   when it refused the site's key
     */
    public function classify(Submission $submission): Classification;

    /**
     * Teach the classifier: $spam true for a spam it let through, false for
     * a ham it held. Send the submission as it was classified.
     *
     * @throws Exception\NotSupportedException when the provider takes no report
     * @throws Exception\UnreachableException
     * @throws Exception\InvalidKeyException
     */
    public function report(Submission $submission, bool $spam): void;
}
