<?php

/**
 * Send email verification email to user
 * @param string $email User's email address
 * @param string $username User's username
 * @param string $token Verification token
 * @return bool True if email sent successfully, false otherwise
 */
function sendVerificationEmail($email, $username, $token) {
    try {
        $mail = require __DIR__ . "/mailer.php";
        
        $mail->setFrom("053printsaturservice@gmail.com", "053 Prints");
        $mail->addAddress($email, $username);
        $mail->Subject = "Verify Your Email Address - 053 Prints";
        
        // Create verification URL
        $verificationUrl = "http://localhost/Capstone2/verify_email.php?token=" . $token;
        
        $mail->Body = <<<END
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f4f4f4; 
                }
                .container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background: white; 
                    border-radius: 12px; 
                    overflow: hidden; 
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
                }
                .header { 
                    background: #1a1a1a; 
                    color: white; 
                    padding: 30px 20px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 24px; 
                    font-weight: 700; 
                    letter-spacing: 2px; 
                    margin-bottom: 8px; 
                }
                .header .subtitle { 
                    font-size: 16px; 
                    font-weight: 400; 
                    margin: 0; 
                    opacity: 0.9; 
                }
                .content { 
                    padding: 40px 30px; 
                    background: white; 
                }
                .greeting { 
                    font-size: 18px; 
                    color: #333; 
                    margin-bottom: 20px; 
                }
                .message { 
                    color: #333; 
                    margin-bottom: 30px; 
                    font-size: 16px; 
                    line-height: 1.5; 
                }
                .button { 
                    display: inline-block; 
                    background: #4A90E2; 
                    color:rgb(255, 255, 255); 
                    padding: 15px 40px; 
                    text-decoration: none; 
                    border-radius: 25px; 
                    font-weight: 600; 
                    font-size: 14px; 
                    text-transform: uppercase; 
                    letter-spacing: 1px; 
                    margin: 20px 0; 
                    text-align: left; 
                }
                .alternative { 
                    color: #666; 
                    font-size: 14px; 
                    margin: 30px 0 10px 0; 
                }
                .link { 
                    color: #333; 
                    word-break: break-all; 
                    font-size: 14px; 
                    margin-bottom: 30px; 
                }
                .disclaimer { 
                    color: #666; 
                    font-size: 14px; 
                    margin: 30px 0; 
                }
                .signature { 
                    color: #333; 
                    font-size: 16px; 
                    margin-top: 30px; 
                }
                .footer { 
                    text-align: center; 
                    padding: 30px; 
                    background: #f8f9fa; 
                    color: #888; 
                    font-size: 14px; 
                    line-height: 1.4; 
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>053 PRINTS</h1>
                    <div class="subtitle">Email Verification</div>
                </div>
                <div class="content">
                    <div class="greeting">Dear $username,</div>
                    
                    <div class="message">
                        Thank you for registering with 053 Prints! To complete your registration and start using our custom printing services, please verify your email address.
                    </div>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="$verificationUrl" class="button">VERIFY EMAIL ADDRESS</a>
                    </div>
                    
                    <div class="alternative">Or copy and paste this link into your browser:</div>
                    <div class="link">$verificationUrl</div>
                    
                    <div class="disclaimer">If you didn't create an account with us, please ignore this email.</div>
                    
                    <div class="signature">
                        Best regards,<br>
                        The 053 Prints Team
                    </div>
                </div>
                <div class="footer">
                    053 Prints - Custom prints and designs made to bring your ideas to life.<br>
                    53 San Ignacio St. Poblacion 1 3023 San Jose del Monte, Philippines
                </div>
            </div>
        </body>
        </html>
        END;
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Email verification send error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate a secure verification token
 * @return string 32-character verification token
 */
function generateVerificationToken() {
    return bin2hex(random_bytes(32));
}
?>
