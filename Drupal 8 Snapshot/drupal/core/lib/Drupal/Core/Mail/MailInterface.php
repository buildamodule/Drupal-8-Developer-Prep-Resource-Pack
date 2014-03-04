<?php

/**
 * @file
 * Definition of Drupal\Core\Mail\MailInterface.
 */

namespace Drupal\Core\Mail;

/**
 * Defines an interface for pluggable mail back-ends.
 */
interface MailInterface {

  /**
   * Formats a message composed by drupal_mail() prior sending.
   *
   * @param array $message
   *   A message array, as described in hook_mail_alter().
   *
   * @return array
   *   The formatted $message.
   */
   public function format(array $message);

  /**
   * Sends a message composed by drupal_mail().
   *
   * @param array $message
   *   Message array with at least the following elements:
   *   - id: A unique identifier of the e-mail type. Examples: 'contact_user_copy',
   *     'user_password_reset'.
   *   - to: The mail address or addresses where the message will be sent to.
   *     The formatting of this string will be validated with the
   *     @link http://php.net/manual/filter.filters.validate.php PHP e-mail validation filter. @endlink
   *     Some examples:
   *     - user@example.com
   *     - user@example.com, anotheruser@example.com
   *     - User <user@example.com>
   *     - User <user@example.com>, Another User <anotheruser@example.com>
   *   - subject: Subject of the e-mail to be sent. This must not contain any
   *     newline characters, or the mail may not be sent properly.
   *   - body: Message to be sent. Accepts both CRLF and LF line-endings.
   *     E-mail bodies must be wrapped. You can use drupal_wrap_mail() for
   *     smart plain text wrapping.
   *   - headers: Associative array containing all additional mail headers not
   *     defined by one of the other parameters.  PHP's mail() looks for Cc and
   *     Bcc headers and sends the mail to addresses in these headers too.
   *
   * @return bool
   *   TRUE if the mail was successfully accepted for delivery, otherwise FALSE.
   */
   public function mail(array $message);
}
