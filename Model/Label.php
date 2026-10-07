<?php

namespace Omniguard\Model;

/** What a classifier made of a submission. */
enum Label: string
{
    /** Not spam. */
    case HAM = 'ham';
    /** Spam: hold it for a person to look at. */
    case SPAM = 'spam';
    /** Spam beyond doubt: it may be dropped unseen. */
    case FLAGRANT = 'flagrant';
}
