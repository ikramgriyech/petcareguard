<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
         @import url('https://fonts.cdnfonts.com/css/cooper-black');
        footer {
    background: #4ecdc4;
    color: white;
    padding: 3rem 0 1rem;
 
}

.footer-content {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
    margin-bottom: 2rem;
}

.footer-section h3 {
    margin-bottom: 1rem;
}

.footer-section p {
    margin-bottom: 0.5rem;
}

.footer-bottom {
    text-align: center;
    padding-top: 2rem;
    border-top: 1px solid rgba(255,255,255,0.2);
    font-size: 0.9rem;
}

.dog-peek {
    position: fixed;
    bottom: 0;
    right: 2rem;
    width: 100px;
    z-index: 100;
}

.dog-peek img {
    width: 100%;
    height: auto;
}

/* Responsive */
@media (max-width: 768px) {
    .hero-content,
    .about-content,
    .ideas-content {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .hero-text h1 {
        font-size: 2rem;
    }

    .steps {
        grid-template-columns: 1fr;
    }

    .comment-form {
        flex-direction: column;
    }

    .footer-content {
        grid-template-columns: 1fr;
    }
}

    </style>
</head>
<body>
     <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Facebook</h3>
                    <h3>Instagram</h3>
                </div>
                <div class="footer-section">
                    <p>Liens vers les réseaux sociaux du tournoi</p>
                    <p>Informations pratiques</p>
                    <p>+212 76 77 89 43 22</p>
                </div>
            </div>
            <div class="footer-bottom">
                © 2023 WebDev Solutions. Tous droits réservés
            </div>
        </div>
    </footer>
</body>
</html>