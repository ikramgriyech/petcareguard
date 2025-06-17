<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Footer</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: #f8fafc;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Simple Footer */
        footer {
            background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
            color: white;
            padding: 2rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }

        .footer-section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            text-decoration: none;
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .social-icon {
            font-size: 1rem;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            padding: 0.25rem 0;
        }

        .contact-icon {
            font-size: 0.9rem;
            width: 1.5rem;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.3);
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* Demo content */
        .demo-content {
            padding: 3rem 0;
            text-align: center;
            background: white;
        }

        .demo-content h1 {
            font-size: 2rem;
            color: #2d3748;
            margin-bottom: 1rem;
        }

        .demo-content p {
            color: #718096;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                text-align: center;
            }

            .social-links {
                justify-content: center;
                flex-wrap: wrap;
            }

            .contact-info {
                align-items: center;
            }

            footer {
                padding: 1.5rem 0 1rem;
            }
        }

        @media (max-width: 480px) {
            .social-links {
                flex-direction: column;
                align-items: center;
            }

            .social-link {
                width: 100%;
                max-width: 200px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Demo Content -->
 

    <!-- Simple Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Réseaux Sociaux</h3>
                    <div class="social-links">
                        <a href="#" class="social-link">
                            <i class="fab fa-facebook-f social-icon"></i>
                            <span>Facebook</span>
                        </a>
                        <a href="#" class="social-link">
                            <i class="fab fa-instagram social-icon"></i>
                            <span>Instagram</span>
                        </a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Contact</h3>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-share-alt contact-icon"></i>
                            <span>Liens réseaux sociaux</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-info-circle contact-icon"></i>
                            <span>Informations pratiques</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone contact-icon"></i>
                            <span>+212 76 77 89 43 22</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                © 2023 WebDev Solutions. Tous droits réservés
            </div>
        </div>
    </footer>
</body>
</html>