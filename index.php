<?php
require_once __DIR__ . '/includes/session_params.php';
session_start();
// if (isset($_SESSION['user_id'])) {
//     echo "";
//     exit;
// }

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
include_once __DIR__ . '/includes/fetch_units.php';

function landing_unit_name(array $unit): string
{
    $rawNum = trim(preg_replace('/^unit\s*/i', '', $unit['unit_number'] ?? ''));
    if (!empty($unit['unit_name'])) {
        return $unit['unit_name'];
    }
    if (!empty($unit['property_name']) && $rawNum !== '') {
        return $unit['property_name'] . ' — Unit ' . $rawNum;
    }
    if (!empty($unit['unit_number'])) {
        return $unit['unit_number'];
    }
    return 'Unit #' . ($unit['unit_id'] ?? '');
}

$landingUnits = array_slice($units ?? [], 0, 6);
$heroUnits = array_slice($landingUnits, 0, 5);

$landingReviews = [];
if (isset($conn) && $conn) {
    $reviewsRes = mysqli_query($conn, "
        SELECT br.rating, br.comment, br.created_at,
               u.first_name, u.last_name, u.profile_photo,
               COALESCE(un.unit_name, un.unit_number, 'Room') AS room_name,
               p.property_name
        FROM booking_reviews br
        JOIN users u ON u.user_id = br.user_id
        LEFT JOIN units un ON un.unit_id = br.unit_id
        LEFT JOIN properties p ON p.property_id = un.property_id
        LEFT JOIN bookings b ON b.booking_id = br.booking_id
        WHERE b.status = 'completed'
        ORDER BY RAND()
        LIMIT 6
    ");
    while ($reviewsRes && ($rr = mysqli_fetch_assoc($reviewsRes))) {
        $landingReviews[] = $rr;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boracay Accommodation — Investment Properties & Services</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" type="image/png" href="assets/images/logo.png"/>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap"
        rel="stylesheet">
</head>

<body>

    <header id="hdr">
        <a href="#" class="logo">
            <img src="assets/images/logo.png" alt="Boracay Accommodation Logo" class="logo-icon">
            <span>
                <span style="display:block;line-height:1.1;">Boracay Accommodation</span>
                <span
                    style="display:block;font-family:'Jost',sans-serif;font-size:0.45rem;font-weight:500;letter-spacing:0.12em;color:var(--text-soft);text-transform:uppercase;">Investment
                    Properties & Services</span>
            </span>
        </a>

        <nav>
            <a href="#hero">Home</a>
            <a href="#about">About</a>
            <a href="#rooms">Rooms</a>
            <a href="#reviews">Reviews</a>
            <a href="#contact">Contact</a>
        </nav>

        <div style="display:flex;align-items:center;gap:0.75rem;">
            <button class="btn-login-header">Log In</button>
            <button class="btn-book-header"
                onclick="document.querySelector('#cta').scrollIntoView({behavior:'smooth'})">Book Now</button>
            <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
        </div>
    </header>

    <div class="mobile-nav" id="mobileNav">
        <a href="#hero" onclick="closeMob()">Home</a>
        <a href="#about" onclick="closeMob()">About</a>
        <a href="#rooms" onclick="closeMob()">Rooms</a>
        <a href="#reviews" onclick="closeMob()">Reviews</a>
        <a href="#contact" onclick="closeMob()">Contact</a>
        <a href="#cta" onclick="closeMob()" style="color:var(--gold);font-weight:600;">Book Now</a>
        <button class="btn-login-header">Log In</button>
    </div>

    <div class="modal-overlay" id="loginModal">
        <div class="modal-box">
            <button class="modal-close" id="modalClose">&times;</button>
            <div id="modalAlert" style="display:none;"></div>
            
            <form id="loginForm">
                <div class="modal-form" id="tab-login">
                    <div class="modal-header">
                        <h2 class="modal-title">Welcome Back</h2>
                        <p class="modal-sub">Log in to continue your booking</p>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="modal-field">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>

                    <div class="modal-field">
                        <label>Password</label>
                        <div class="password-field-wrap">
                            <input id="loginPassword" type="password" name="password" placeholder="••••••••" required>
                            <button type="button" class="toggle-password" data-target="loginPassword" aria-label="Show password" title="Show password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button class="modal-btn-primary" type="submit">Log In</button>

                    <p style="text-align:center;margin:10px 0 0;font-size:13px;">
                        <a href="#" onclick="openForgotModal();return false;"
                           style="color:#1e3a5f;font-weight:500;text-decoration:none;">
                           Forgot your password?
                        </a>
                    </p>

                    <p class="modal-switch">
                        Don't have an account?
                        <a href="#" onclick="switchTab('signup')">Sign Up</a>
                    </p>
                </div>
            </form>

            <form id="registerForm">
                <div class="modal-form" id="tab-signup">
                    <div class="modal-header">
                        <h2 class="modal-title">Create Account</h2>
                        <p class="modal-sub">Sign up to book your stay</p>
                    </div>
                    <div class="modal-field">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    </div>

                    <div class="field-name-row">
                        <div class="modal-field">
                            <label>First Name</label>
                            <input type="text" name="first_name" placeholder="First Name" required>
                        </div>
                        <div class="modal-field">
                            <label>Last Name</label>
                            <input type="text" name="last_name" placeholder="Last Name" required>
                        </div>
                    </div>

                    <div class="modal-field">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>

                    <div class="field-phone-row">
                        <div class="modal-field">
                            <label>Country Code</label>
                            <select id="signupCountryCode" name="country_code" required autocomplete="tel-country-code">
                                <option value="">Select code</option>
                                <option value="+63" selected>🇵🇭 +63</option>
                                <option value="+1">🇺🇸 +1</option>
                                <option value="+44">🇬🇧 +44</option>
                                <option value="+61">🇦🇺 +61</option>
                                <option value="+65">🇸🇬 +65</option>
                                <option value="+81">🇯🇵 +81</option>
                                <option value="+82">🇰🇷 +82</option>
                                <option value="+91">🇮🇳 +91</option>
                                <option value="+971">🇦🇪 +971</option>
                                <option value="+966">🇸🇦 +966</option>
                                <option value="+974">🇶🇦 +974</option>
                                <option value="+973">🇧🇭 +973</option>
                                <option value="+965">🇰🇼 +965</option>
                            </select>
                            <small id="countryCodeHint" style="display:block;margin-top:6px;color:var(--text-soft);font-size:.72rem;">Use your local number without leading 0.</small>
                        </div>
                        <div class="modal-field">
                            <label>Phone Number</label>
                            <input id="signupPhoneNumber" type="tel" name="phone" placeholder="9123456789" inputmode="numeric" maxlength="10" required>
                        </div>
                    </div>

                    <div class="modal-field">
                        <label>Password</label>
                        <div class="password-field-wrap">
                            <input id="signupPassword" type="password" name="password" placeholder="••••••••" required minlength="6">
                            <button type="button" class="toggle-password" data-target="signupPassword" aria-label="Show password" title="Show password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                        <div style="margin-top:8px;">
                            <div style="height:6px;background:#e0eefa;border-radius:999px;overflow:hidden;">
                                <div id="signupPasswordStrengthBar" style="height:100%;width:0;background:#ef4444;transition:all .2s ease;"></div>
                            </div>
                            <small id="signupPasswordStrengthText" style="display:block;margin-top:6px;color:var(--text-soft);font-size:.72rem;">Password strength: Weak</small>
                        </div>
                    </div>

                    <div class="modal-field">
                        <label>Confirm Password</label>
                        <div class="password-field-wrap">
                            <input id="signupConfirmPassword" type="password" name="confirm_password" placeholder="••••••••" required>
                            <button type="button" class="toggle-password" data-target="signupConfirmPassword" aria-label="Show password" title="Show password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                        <small id="signupConfirmPasswordAlert" style="display:none;margin-top:6px;color:#dc2626;font-size:.72rem;"></small>
                    </div>

                    <div class="form-field" style="margin-bottom:14px;">
                        <label style="display:block;margin-bottom:6px;font-size:.8rem;font-weight:600;">Country</label>
                        <select name="nationality" style="width:100%;padding:10px 12px;border:1.5px solid var(--border,#e2e8f0);border-radius:8px;font-size:.85rem;font-family:inherit;background:#fff;color:var(--text-dark);">
                            <option value="">Select your country</option>
                            <?php
                            $reg_countries = ["Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda","Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cabo Verde","Cambodia","Cameroon","Canada","Central African Republic","Chad","Chile","China","Colombia","Comoros","Congo","Costa Rica","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea","Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea","Guinea-Bissau","Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kiribati","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Marshall Islands","Mauritania","Mauritius","Mexico","Micronesia","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan","Palau","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saint Kitts and Nevis","Saint Lucia","Saint Vincent and the Grenadines","Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","South Korea","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan","Vanuatu","Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"];
                            foreach ($reg_countries as $c) {
                                echo '<option value="' . htmlspecialchars($c) . '">' . htmlspecialchars($c) . "</option>\n";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="signup-consent">
                        <label class="signup-consent-row">
                            <input id="signupAgreeTerms" type="checkbox" required>
                            <span>
                                I agree to the
                                <a href="#" class="policy-link" data-policy="terms">Terms</a>,
                                <a href="#" class="policy-link" data-policy="privacy">Privacy Policy</a>,
                                and <a href="#" class="policy-link" data-policy="booking">Booking Policy</a>.
                            </span>
                        </label>
                        <small id="signupConsentAlert" class="signup-consent-alert"></small>
                    </div>

                    <button class="modal-btn-primary" type="submit" name="register">
                        Create Account
                    </button>

                    <p class="modal-switch">
                        Already have an account?
                        <a href="#" onclick="switchTab('login')">Log In</a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <section class="hero" id="hero">
        <div class="hero-text">
            <div class="hero-eyebrow">
                <span class="eyebrow-line"></span>
                Welcome to Boracay Accommodation
            </div>
            <h1>Experience the<br>Warmth of<br><em>Filipino Hospitality</em></h1>
            <p class="hero-desc">
                Located on the vibrant shores of Boracay, Boracay Accommodation offers stylish apartment accommodations where
                modern comfort meets Filipino hospitality. From sunlit balconies with ocean views to thoughtfully
                furnished interiors, every space is designed for relaxation and a true sense of <em
                    style="font-style:normal;color:var(--terra)">home</em>.
            </p>
            <div class="hero-stats">
                <div>
                    <div class="hero-stat-num">48+</div>
                    <div class="hero-stat-lbl">Unique Rooms</div>
                </div>
                <div>
                    <div class="hero-stat-num">4.9★</div>
                    <div class="hero-stat-lbl">Guest Rating</div>
                </div>
                <div>
                    <div class="hero-stat-num">12yr</div>
                    <div class="hero-stat-lbl">of Hospitality</div>
                </div>
            </div>
        </div>

        <div class="hero-carousel">
            <div class="carousel-frame" id="carouselFrame">
                <div class="carousel-slides" id="carouselSlides">

                    <?php if (!empty($heroUnits)): ?>
                        <?php foreach ($heroUnits as $idx => $unit):
                            $heroName = landing_unit_name($unit);
                            $heroType = $unit['unit_type'] ?: 'Room';
                            $heroImg = !empty($unit['image_path']) ? ltrim($unit['image_path'], '/') : 'assets/images/placeholder.jpg';
                            ?>
                            <div class="carousel-slide <?php echo $idx === 0 ? 'active' : ''; ?> room-<?php echo ($idx % 5) + 1; ?>"
                                data-label="<?php echo htmlspecialchars($heroName); ?>"
                                data-type="<?php echo htmlspecialchars($heroType); ?>">
                                <img src="<?php echo htmlspecialchars($heroImg); ?>" alt="<?php echo htmlspecialchars($heroName); ?>">
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>

                <div class="carousel-overlay"></div>
                <div class="carousel-label">
                    <div class="carousel-label-name" id="slideLabel"><?php echo !empty($heroUnits) ? htmlspecialchars(landing_unit_name($heroUnits[0])) : 'Featured Room'; ?></div>
                    <div class="carousel-label-type" id="slideType"><?php echo !empty($heroUnits) ? htmlspecialchars($heroUnits[0]['unit_type'] ?: 'Room') : 'View details'; ?></div>
                </div>

                <div class="carousel-controls">
                    <button class="carousel-btn" id="prevBtn" aria-label="Previous">
                        <svg viewBox="0 0 24 24">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                    </button>
                    <button class="carousel-btn" id="nextBtn" aria-label="Next">
                        <svg viewBox="0 0 24 24">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="carousel-dots" id="carouselDots">
                <?php foreach ($heroUnits as $idx => $_hu): ?>
                    <div class="dot <?php echo $idx === 0 ? 'active' : ''; ?>" data-idx="<?php echo $idx; ?>"></div>
                <?php endforeach; ?>
            </div>

            <div class="carousel-thumbs" id="carouselThumbs">
                <?php foreach ($heroUnits as $idx => $unit):
                    $heroName = landing_unit_name($unit);
                    $heroImg = !empty($unit['image_path']) ? ltrim($unit['image_path'], '/') : 'assets/images/placeholder.jpg';
                    ?>
                    <div class="thumb <?php echo $idx === 0 ? 'active' : ''; ?>" data-idx="<?php echo $idx; ?>">
                        <img src="<?php echo htmlspecialchars($heroImg); ?>" alt="<?php echo htmlspecialchars($heroName); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="about" id="about">
        <div class="about-inner">

            <div class="about-visual reveal">
                <div class="about-img-main">
                    <img src="assets/images/owner.jpg" alt="Owner of Boracay Accommodation">
                </div>
                <div class="about-img-accent">
                    <img src="assets/images/hero-img.jpg" alt="Image of Boracay Accommodation" >
                </div>
                <div class="about-badge">
                    <div class="badge-num">12+</div>
                    <div class="badge-lbl">Years of<br>Hospitality</div>
                </div>
            </div>

            <div class="about-text reveal reveal-delay-1">
                <div class="eyebrow">Our Story</div>
                <h2 class="section-heading">A <em>Home</em> Away<br>From Home</h2>
                <p class="body-text">
                    Since 2012, the Magdaong family has welcomed guests with one simple belief: every visitor deserves
                    the warmth and care of a Filipino household. What began as a small collection of well-designed
                    apartments has grown into a cherished retreat, loved by travelers from all over the world.
                </p>
                <p class="body-text">
                    Each apartment is thoughtfully designed with a mix of contemporary finishes and local touches — from
                    capiz-inspired accents to hand-woven textiles — ensuring comfort without losing character. Our staff
                    treats every guest like family, because at Boracay Accommodation, <em
                        style="font-style:normal;color:var(--terra)">you are truly at home</em>.
                </p>
                <div class="about-pillars">
                    <div class="pillar">
                        <div class="pillar-icon"><svg viewBox="0 0 24 24">
                                <path
                                    d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
                            </svg></div>
                        <div>
                            <div class="pillar-title">Filipino Warmth</div>
                            <div class="pillar-desc">Genuine malasakit in every interaction</div>
                        </div>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon"><svg viewBox="0 0 24 24">
                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                <polyline points="9 22 9 12 15 12 15 22" />
                            </svg></div>
                        <div>
                            <div class="pillar-title">Modern Comfort</div>
                            <div class="pillar-desc">Stylish apartments with Filipino touches.</div>
                        </div>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon"><svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg></div>
                        <div>
                            <div class="pillar-title">24/7 Care</div>
                            <div class="pillar-desc">Always here whenever you need us</div>
                        </div>
                    </div>
                    <div class="pillar">
                        <div class="pillar-icon"><svg viewBox="0 0 24 24">
                                <path d="M12 22s-8-4.5-8-11.8A8 8 0 0112 2a8 8 0 018 8.2c0 7.3-8 11.8-8 11.8z" />
                                <circle cx="12" cy="10" r="3" />
                            </svg></div>
                        <div>
                            <div class="pillar-title">Prime Location</div>
                            <div class="pillar-desc">Steps from Boracay’s beach and attractions</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="rooms" id="rooms">
        <div class="rooms-header reveal">
            <div class="eyebrow" style="justify-content:center;"><span
                    style="width:24px;height:1.5px;background:var(--gold);display:block;"></span>&nbsp;Featured Rooms
            </div>
            <h2 class="section-heading">Find Your <em>Perfect</em> Room</h2>
        </div>

        <div class="rooms-grid">
            <?php if (empty($landingUnits)): ?>
                <div style="grid-column:1/-1;text-align:center;padding:40px 20px;color:var(--text-soft);">
                    No rooms available right now.
                </div>
            <?php else: ?>
                <?php foreach ($landingUnits as $idx => $unit):
                    $name = landing_unit_name($unit);
                    $image = !empty($unit['image_path']) ? ltrim($unit['image_path'], '/') : 'assets/images/placeholder.jpg';
                    $amenities = $amenitiesMap[$unit['unit_id']] ?? [];
                    $location = trim(($unit['property_name'] ?? '') . (!empty($unit['city']) ? ', ' . $unit['city'] : ''));
                    $roomData = [
                        'name' => $name,
                        'type' => $unit['unit_type'] ?: 'Room',
                        'location' => $location,
                        'price' => '₱' . number_format((float)($unit['rent_amount'] ?? 0), 0),
                        'description' => $unit['description'] ?: 'Comfortable room with essential amenities.',
                        'image' => $image,
                        'amenities' => array_map(function ($am) { return $am['name'] ?? ''; }, $amenities),
                    ];
                    $badgeClass = '';
                    $badgeLabel = !empty($unit['unit_type']) ? ucwords(trim((string)$unit['unit_type'])) : 'Unit';
                    ?>
                    <div class="room-card reveal <?php echo $idx % 2 ? 'reveal-delay-1' : ''; ?>">
                        <div class="room-card-img">
                            <div class="room-card-img-bg r-img-<?php echo ($idx % 6) + 1; ?>">
                                <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($name); ?>">
                            </div>
                            <span class="room-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($badgeLabel); ?></span>
                        </div>
                        <div class="room-card-body">
                            <div class="room-name"><?php echo htmlspecialchars($name); ?></div>
                            <div class="room-meta">
                                <span><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg><?php echo htmlspecialchars($unit['unit_type'] ?: 'Room'); ?></span>
                                <span><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" /></svg>2 Guests</span>
                            </div>
                            <div class="room-divider"></div>
                            <div class="room-price-row">
                                <div class="room-price">₱<?php echo number_format((float)($unit['rent_amount'] ?? 0), 0); ?> <sub>/ night</sub></div>
                                <button class="btn-room" data-room="<?php echo htmlspecialchars(json_encode($roomData), ENT_QUOTES); ?>">View Room</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="testimonials" id="reviews">
        <div class="testimonials-header reveal">
            <div class="eyebrow">Guest Reviews</div>
            <h2 class="section-heading" style="color:var(--white);">What Our Guests <em
                    style="color:var(--gold)">Say</em></h2>
        </div>

        <div class="testi-grid">
            <?php if (empty($landingReviews)): ?>
                <div class="testi-empty reveal">
                    Guest reviews will appear here once our first visitors share their stay experience.
                </div>
            <?php else: ?>
                <?php foreach ($landingReviews as $ri => $rv):
                    $fullName = trim(($rv['first_name'] ?? '') . ' ' . ($rv['last_name'] ?? ''));
                    $initials = strtoupper(substr($rv['first_name'] ?? 'G', 0, 1) . substr($rv['last_name'] ?? 'U', 0, 1));
                    $rating = max(1, min(5, (int)($rv['rating'] ?? 0)));
                    $avatarClass = 'av-' . (($ri % 6) + 1);
                    $reviewPhotoRaw = trim((string)($rv['profile_photo'] ?? ''));
                    $reviewPhoto = $reviewPhotoRaw !== '' ? ltrim($reviewPhotoRaw, '/') : '';
                ?>
                    <div class="testi-card reveal <?php echo $ri % 2 ? 'reveal-delay-1' : ''; ?>">
                        <span class="testi-quote-icon">"</span>
                        <div class="testi-stars">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <svg viewBox="0 0 24 24" style="<?php echo $s <= $rating ? '' : 'opacity:.25;'; ?>">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <div class="testi-text"><?php echo htmlspecialchars($rv['comment'] ?? 'Great stay!'); ?></div>
                        <div class="testi-author">
                            <div class="testi-avatar <?php echo $avatarClass; ?>">
                                <?php if ($reviewPhoto): ?>
                                    <img src="<?php echo htmlspecialchars($reviewPhoto); ?>" alt="<?php echo htmlspecialchars($fullName ?: 'Guest'); ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <?php endif; ?>
                                <span class="testi-avatar-initials" <?php echo $reviewPhoto ? 'style="display:none;"' : ''; ?>>
                                    <?php echo htmlspecialchars($initials); ?>
                                </span>
                            </div>
                            <div>
                                <div class="testi-name"><?php echo htmlspecialchars($fullName ?: 'Guest'); ?></div>
                                <?php
                                    $propertyLabel = trim($rv['property_name'] ?? '');
                                    $unitLabel = trim($rv['room_name'] ?? '');
                                    $locationLine = $propertyLabel !== '' && $unitLabel !== '' && $unitLabel !== $propertyLabel
                                        ? $propertyLabel . ' · ' . $unitLabel
                                        : ($propertyLabel ?: $unitLabel ?: 'Room');
                                ?>
                                <div class="testi-location"><?php echo htmlspecialchars($locationLine); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>

        <?php if (!empty($landingReviews)): ?>
        <!-- Mobile-only swiper (shown via CSS at <=768px) -->
        <div class="testi-swiper-wrap" id="testiSwiper">
            <div class="testi-swiper-track">
                <div class="testi-swiper-inner" id="testiSwiperInner">
                <?php foreach ($landingReviews as $ri => $rv):
                    $fullName = trim(($rv['first_name'] ?? '') . ' ' . ($rv['last_name'] ?? ''));
                    $initials = strtoupper(substr($rv['first_name'] ?? 'G', 0, 1) . substr($rv['last_name'] ?? 'U', 0, 1));
                    $rating = max(1, min(5, (int)($rv['rating'] ?? 0)));
                    $avatarClass = 'av-' . (($ri % 6) + 1);
                    $reviewPhotoRaw = trim((string)($rv['profile_photo'] ?? ''));
                    $reviewPhoto = $reviewPhotoRaw !== '' ? ltrim($reviewPhotoRaw, '/') : '';
                    $propertyLabel = trim($rv['property_name'] ?? '');
                    $unitLabel = trim($rv['room_name'] ?? '');
                    $locationLine = $propertyLabel !== '' && $unitLabel !== '' && $unitLabel !== $propertyLabel
                        ? $propertyLabel . ' · ' . $unitLabel
                        : ($propertyLabel ?: $unitLabel ?: 'Room');
                ?>
                    <div class="testi-card">
                        <span class="testi-quote-icon">"</span>
                        <div class="testi-stars">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <svg viewBox="0 0 24 24" style="<?php echo $s <= $rating ? '' : 'opacity:.25;'; ?>">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <div class="testi-text"><?php echo htmlspecialchars($rv['comment'] ?? 'Great stay!'); ?></div>
                        <div class="testi-author">
                            <div class="testi-avatar <?php echo $avatarClass; ?>">
                                <?php if ($reviewPhoto): ?>
                                    <img src="<?php echo htmlspecialchars($reviewPhoto); ?>" alt="<?php echo htmlspecialchars($fullName ?: 'Guest'); ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <?php endif; ?>
                                <span class="testi-avatar-initials" <?php echo $reviewPhoto ? 'style="display:none;"' : ''; ?>>
                                    <?php echo htmlspecialchars($initials); ?>
                                </span>
                            </div>
                            <div>
                                <div class="testi-name"><?php echo htmlspecialchars($fullName ?: 'Guest'); ?></div>
                                <div class="testi-location"><?php echo htmlspecialchars($locationLine); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="testi-swiper-nav">
            <button class="testi-swiper-btn" id="testiPrev" aria-label="Previous review">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <div class="testi-swiper-dots" id="testiDots">
                <?php foreach ($landingReviews as $ri => $_r): ?>
                    <div class="testi-swiper-dot <?php echo $ri === 0 ? 'active' : ''; ?>" data-idx="<?php echo $ri; ?>"></div>
                <?php endforeach; ?>
            </div>
            <button class="testi-swiper-btn" id="testiNext" aria-label="Next review">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
        </div>
        <?php endif; ?>

    </section>

    <section class="cta-section" id="cta">
        <div class="cta-pattern"></div>
        <div class="eyebrow cta-eyebrow reveal" style="justify-content:center;">
            <span style="width:24px;height:1.5px;background:var(--gold);display:block;"></span>
            &nbsp; Start Your Journey
        </div>
        <h2 class="cta-heading reveal">
            Where Every Stay<br>Feels Like <em>Coming Home</em>
        </h2>
        <p class="cta-sub reveal">
            From the gentle sea breeze at dawn to the glow of Boracay’s sunsets, your most memorable Filipino experience
            awaits. Book your stay at Boracay Accommodation in Boracay today.
        </p>
        <button class="btn-book-big reveal">
            Book Your Stay Now
            <svg viewBox="0 0 24 24">
                <line x1="5" y1="12" x2="19" y2="12" />
                <polyline points="12 5 19 12 12 19" />
            </svg>
        </button>
        <p class="cta-note reveal">Free cancellation up to 48 hours before check-in · No hidden fees</p>
    </section>

    <footer id="contact">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="logo" style="margin-bottom:1rem;display:inline-flex;">
                    <img src="assets/images/logo.png" alt="Boracay Accommodation Logo" class="logo-icon-2">
                    Boracay Accommodation
                </a>
                <p>A modern apartment retreat on Boracay, blending authentic Filipino warmth with contemporary comfort.
                </p>
                <div class="social-row">
                    <a href="#" class="soc" aria-label="Facebook">
                        <svg fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                        </svg>
                    </a>
                    <a href="#" class="soc" aria-label="Instagram">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="2" y="2" width="20" height="20" rx="5" />
                            <circle cx="12" cy="12" r="4" />
                            <circle cx="17.5" cy="6.5" r=".5" fill="currentColor" />
                        </svg>
                    </a>
                    <a href="#" class="soc" aria-label="TripAdvisor">
                        <svg fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18a8 8 0 110-16 8 8 0 010 16zm-1-5a1 1 0 102 0 1 1 0 00-2 0zm-4-4a1 1 0 102 0 1 1 0 00-2 0zm8 0a1 1 0 102 0 1 1 0 00-2 0z" />
                        </svg>
                    </a>
                </div>
            </div>

            <div class="footer-col">
                <h5>Navigate</h5>
                <ul class="footer-links">
                    <li><a href="#hero">Home</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#rooms">Rooms</a></li>
                    <li><a href="#reviews">Reviews</a></li>
                    <li><a href="#cta">Book Now</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h5>Contact Us</h5>
                <div class="contact-row">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                        <circle cx="12" cy="10" r="3" />
                    </svg>
                    Station 3, Barangay Manoc-Manoc,<br>Boracay Island, Aklan 5608
                </div>
                <div class="contact-row">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z" />
                    </svg>
                    +63 33 123 4567
                </div>
                <div class="contact-row">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                    hello@filipinohomes.ph
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <span>© 2025 Boracay Accommodation Investment Properties & Services. All rights reserved.</span>
            <span class="footer-policy-links">
                <a href="#" class="footer-policy-link policy-link" data-policy="privacy">Privacy Policy</a>
                <span>·</span>
                <a href="#" class="footer-policy-link policy-link" data-policy="terms">Terms</a>
                <span>·</span>
                <a href="#" class="footer-policy-link policy-link" data-policy="booking">Booking Policy</a>
            </span>
        </div>
    </footer>

    <script src="assets/js/toast.js"></script>
    
    <div class="room-preview-overlay" id="roomPreviewModal">
        <div class="room-preview-box">
            <button class="room-preview-close" id="roomPreviewClose">&times;</button>
            <div class="room-preview-media">
                <img id="roomPreviewImage" src="assets/images/placeholder.jpg" alt="Room image">
            </div>
            <div class="room-preview-body">
                <h3 id="roomPreviewName">Room</h3>
                <p id="roomPreviewType" class="room-preview-type"></p>
                <p id="roomPreviewLocation" class="room-preview-location"></p>
                <p id="roomPreviewDesc" class="room-preview-desc"></p>
                <div id="roomPreviewAmenities" class="room-preview-amenities"></div>
                <div class="room-preview-footer">
                    <div id="roomPreviewPrice" class="room-preview-price"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- ── Forgot Password Modal ── -->
    <div class="modal-overlay" id="forgotModal">
        <div class="modal-box" style="max-width:420px;">
            <button class="modal-close" onclick="closeForgotModal()">&times;</button>
            <div id="forgotAlert" style="display:none;margin-bottom:16px;padding:12px 14px;border-radius:8px;font-size:13px;line-height:1.5;"></div>
            <div id="forgotFormWrap">
                <div class="modal-header">
                    <h2 class="modal-title" style="font-size:1.35rem;">Forgot Password?</h2>
                    <p class="modal-sub">Enter the email you signed up with and we'll send you a reset link.</p>
                </div>
                <div class="modal-field" style="margin-top:20px;">
                    <label>Email Address</label>
                    <input type="email" id="forgotEmail" placeholder="your@email.com" autocomplete="email">
                </div>
                <button class="modal-btn-primary" id="forgotSubmitBtn" onclick="submitForgotPassword()" style="width:100%;margin-top:4px;">
                    Send Reset Link
                </button>
                <p style="text-align:center;margin-top:14px;font-size:13px;color:#6b7280;">
                    Remembered it? <a href="#" onclick="closeForgotModal();openModal('login');return false;" style="color:#1e3a5f;font-weight:600;text-decoration: none;">Back to Login</a>
                </p>
            </div>
        </div>
    </div>

    <div class="policy-modal-overlay" id="policyModal">
        <div class="policy-modal-box">
            <button class="policy-modal-close" id="policyModalClose" aria-label="Close policy modal">&times;</button>
            <h3 class="policy-modal-title" id="policyModalTitle">Policy</h3>
            <div class="policy-modal-meta">Last updated: April 2026</div>
            <div class="policy-modal-content" id="policyModalContent"></div>
        </div>
    </div>
    <script src="assets/js/index.js"></script>

    <?php
    if (isset($_SESSION['alert'])) {
        $type = $_SESSION['alert']['type']; 
        $message = $_SESSION['alert']['message'];
        echo "";
        unset($_SESSION['alert']); 
    }
    ?>

<!-- <script src="assets/js/index-inline.js"></script> -->
</body>
</html>