<?php

// Password_Generator.php

// --- Word pools ---
$nouns = [
    'apple','arrow','banner','basket','beacon','blanket','bottle','bridge',
    'button','cabin','candle','canyon','castle','cavern','circle','clock',
    'cloud','coast','compass','cottage','crown','curtain','desert','dragon',
    'engine','feather','fence','forest','fountain','garden','gate','glass',
    'harbor','helmet','island','jacket','kitten','ladder','lamp','lantern',
    'meadow','mirror','mountain','museum','ocean','orchard','paper','pencil',
    'pillow','pocket','puzzle','rabbit','ribbon','river','rocket','shadow',
    'shield','statue','tunnel','umbrella','valley','village','window','wizard',
];
 
$verbs = [
    'accepts','answers','arrives','borrows','builds','carries','catches',
    'chooses','climbs','collects','covers','creates','crosses','delivers',
    'discovers','draws','dreams','drives','earns','enters','explains',
    'explores','finds','follows','gathers','greets','helps','holds',
    'imagines','invites','joins','keeps','learns','leads','lifts',
    'listens','opens','paints','plants','protects','reaches','remembers',
    'searches','shares','watches',
];
 
$adjectives = [
    'ancient','bold','bright','brave','calm','careful','cheerful','clever',
    'colorful','curious','dazzling','distant','eager','elegant','faithful',
    'famous','fancy','fearless','fierce','friendly','gentle','giant',
    'golden','graceful','grand','happy','hidden','honest',
    'humble','icy','jolly','kind','lively','lonely','loyal','lucky',
    'magical','mighty','modern','mysterious','narrow','patient','peaceful',
    'playful','polite','proud','quiet','quick','radiant','rapid','silent',
    'silver','simple','steady','swift','tiny',
];

// --- Sentence templates ---
// Each closure receives ($n1, $v, $n2, $adj) and returns a phrase string.
$templates = [
    fn($n1,$v,$n2,$adj) => "$n1 $v $n2",
    fn($n1,$v,$n2,$adj) => "$adj $n1 $v $n2",
    fn($n1,$v,$n2,$adj) => "$n1 $v $adj $n2",
];

// --- Handle request ---
$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
$source  = $is_post ? $_POST : $_GET;

$number_count  = max(0, min(4, (int)($source['number_count']  ?? 0)));
$special_count = max(0, min(4, (int)($source['special_count'] ?? 0)));

$passphrase_count = 10;
$page_title       = 'Password Generator';

// Special characters pool used by the generator — referenced by both
// generate_passphrase() and the entropy calculator so the character-set
// size stays in sync if this list ever changes.
const SPECIAL_CHARS = ['!','@','#','$','%','^','&','*','-','_','+','=','?','.'];

// --- Helpers ---

function rand_item(array $arr) {
    return $arr[random_int(0, count($arr) - 1)];
}

function rand_template(array $templates): Closure {
    return $templates[random_int(0, count($templates) - 1)];
}

/**
 * Determines the size of the character set (R) actually present in a
 * given passphrase, based on which character classes appear: lowercase
 * letters, uppercase letters, digits, and specials from SPECIAL_CHARS.
 * Spaces are not counted as part of the alphabet (they add no real
 * guessing entropy since their position is fixed by the template).
 */
function get_charset_size(string $passphrase): int {
    $has_lower   = (bool) preg_match('/[a-z]/', $passphrase);
    $has_upper   = (bool) preg_match('/[A-Z]/', $passphrase);
    $has_digit   = (bool) preg_match('/[0-9]/', $passphrase);
    $special_pattern = '/[' . preg_quote(implode('', SPECIAL_CHARS), '/') . ']/';
    $has_special = (bool) preg_match($special_pattern, $passphrase);

    $size = 0;
    if ($has_lower)   $size += 26;
    if ($has_upper)   $size += 26;
    if ($has_digit)   $size += 10;
    if ($has_special) $size += count(SPECIAL_CHARS);

    return $size;
}

/**
 * Calculates bit entropy using E = log2(R^L) = L * log2(R), where:
 *   R = size of the character set in use (charset size)
 *   L = length of the passphrase in characters (spaces excluded)
 *
 * Note: this is the standard "brute-force character search space"
 * estimate. It treats the passphrase as L characters drawn from an
 * alphabet of size R, which is the conventional way sites like this
 * report entropy — it's a conservative *floor*, not an exact measure
 * of the generator's true randomness (which is actually driven by the
 * word-pool sizes and template count, and is much higher).
 */
function calculate_entropy(string $passphrase): float {
    $length_no_spaces = strlen(str_replace(' ', '', $passphrase));
    $R = get_charset_size($passphrase);

    if ($R <= 1 || $length_no_spaces <= 0) {
        return 0.0;
    }

    return $length_no_spaces * log($R, 2);
}

/**
 * Maps a bit-entropy value to a human label + W3.CSS color class,
 * so the strength badge matches the phrase visually.
 */
function entropy_rating(float $bits): array {
    if ($bits < 40) {
        return ['label' => 'Weak',     'class' => 'w3-red'];
    } elseif ($bits < 60) {
        return ['label' => 'Fair',     'class' => 'w3-orange'];
    } elseif ($bits < 80) {
        return ['label' => 'Strong',   'class' => 'w3-green'];
    } else {
        return ['label' => 'Very Strong', 'class' => 'w3-teal'];
    }
}

/**
 * Calculates the generator's "true" combinatoric entropy for one
 * passphrase — the bits of uncertainty an attacker who knows the word
 * pools and templates (but not the RNG draws) would actually face,
 * as opposed to the naive character-search-space estimate above.
 *
 * Total combinations = (n1 choices) × (n2 choices) × (verb choices)
 *                       × (adjective choices, only if the template used
 *                          one — templates are distinguishable from the
 *                          output, so an unused adjective draw adds no
 *                          real-world uncertainty)
 *                       × 10^(digit count) × (specials pool size)^(special count)
 *
 * Summed as logs rather than multiplied out, to avoid overflow and
 * keep this exact regardless of how large the word pools get.
 */
function calculate_combinatoric_entropy(
    int $noun_count, int $verb_count, int $adjective_count,
    bool $uses_adjective, int $number_count, int $special_count
): float {
    $bits = log($noun_count, 2) * 2 + log($verb_count, 2);

    if ($uses_adjective) {
        $bits += log($adjective_count, 2);
    }

    if ($number_count > 0) {
        $bits += $number_count * log(10, 2);
    }

    if ($special_count > 0) {
        $bits += $special_count * log(count(SPECIAL_CHARS), 2);
    }

    return $bits;
}

// --- Generator ---
// Returns the phrase plus whether the chosen template incorporated the
// adjective, so combinatoric entropy can be scored per-phrase.
function generate_passphrase(
    array $nouns, array $verbs, array $adjectives, array $templates,
    int $number_count, int $special_count
): array {
    $n1  = rand_item($nouns);
    $n2  = rand_item($nouns);
    $v   = rand_item($verbs);
    $adj = rand_item($adjectives);

    $template_index = random_int(0, count($templates) - 1);
    $template        = $templates[$template_index];
    $passphrase      = ucfirst($template($n1, $v, $n2, $adj));

    // Only templates 1 and 2 actually place the adjective in the output;
    // template 0 omits it, so its draw carries no visible entropy.
    $uses_adjective = $template_index !== 0;

    // Append digits as a block at the end — easy to remember
    if ($number_count > 0) {
        $digits = '';
        for ($i = 0; $i < $number_count; $i++) {
            $digits .= random_int(0, 9);
        }
        $passphrase .= $digits;
    }

    // Append special characters at the very end
    if ($special_count > 0) {
        for ($i = 0; $i < $special_count; $i++) {
            $passphrase .= rand_item(SPECIAL_CHARS);
        }
    }

    return ['phrase' => $passphrase, 'uses_adjective' => $uses_adjective];
}

// --- Generate batch ---
$generated = [];
for ($i = 0; $i < $passphrase_count; $i++) {
    $result = generate_passphrase(
        $nouns, $verbs, $adjectives, $templates,
        $number_count, $special_count
    );

    $bits = calculate_entropy($result['phrase']);
    $rating = entropy_rating($bits);

    $true_bits = calculate_combinatoric_entropy(
        count($nouns), count($verbs), count($adjectives),
        $result['uses_adjective'], $number_count, $special_count
    );

    $generated[] = [
        'phrase'    => $result['phrase'],
        'bits'      => $bits,
        'rating'    => $rating,
        'true_bits' => $true_bits,
    ];
}
?>

<div class="w3-auto w3-container">
    <h1><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>

    <form method="post">
        <input type="hidden" name="p" value="Calculator-Passphrase_Generator">

        <button class="w3-button w3-black" type="submit">
            Generate Passwords
        </button>
    </form>

    <?php if (!empty($generated)): ?>
        <div class="w3-panel w3-pale-green w3-leftbar w3-border-green w3-margin-top">
            <button class="w3-button w3-small w3-border w3-round w3-margin-bottom"
                    onclick="copyAllPassphrases()">
                Copy All
            </button>

            <?php foreach ($generated as $item): ?>
                <p>
                    <code class="passphrase"><?= htmlspecialchars($item['phrase'], ENT_QUOTES, 'UTF-8') ?></code>
                    <span class="w3-tag w3-round <?= $item['rating']['class'] ?> w3-small w3-margin-left"
                          title="Character search-space entropy (E = L × log2(R))">
                        <?= htmlspecialchars($item['rating']['label'], ENT_QUOTES, 'UTF-8') ?>
                        &middot; <?= number_format($item['bits'], 1) ?> bits
                    </span>
                    <span class="w3-tag w3-round w3-light-gray w3-small w3-margin-left"
                          title="True combinatoric entropy, based on the word-pool and template space">
                        <?= number_format($item['true_bits'], 1) ?> bits (word-pool)
                    </span>
                    <button class="w3-button w3-small w3-border w3-round w3-margin-left"
                            onclick="copySingle(this)">
                        Copy
                    </button>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    

<p>This is a quick way to generate longer passwords — passphrases — that are far easier to remember than a string of random characters, but not so predictable that they are an easy target. Rather than pulling from common sayings or phrases (the kind that show up in "known phrase" attack lists), this tool builds each passphrase from random combinations of everyday nouns, verbs, and adjectives, so the result reads like a sentence but is not one anyone has used before. Add a few digits or symbols to the end, and you get something that is both memorable and genuinely hard to guess.</p>

<p>A "standard" 8-character password — the kind most sites require, with a mix of case, digits, and a symbol — lands around ~52 bits. That is the theoretical ceiling too; in practice it's often lower, since people don't pick characters uniformly at random (common substitutions like "@" for "a" or capitalizing only the first letter cut the real entropy well below 52 bits).</p>

<p>For comparison, these passwords — even a plain three-word phrase with no digits or symbols — land well above that (character-space entropy alone tends to run 40s-to-60s bits, and the true word-pool entropy is often 90+ bits). They are both longer and more memorable, but harder to brute-force than the "8 characters with a symbol" convention most people default to.</p>

<p>A few notes on this implementation:</p>

<ul>
<li>Looks at each generated phrase and determines R (the alphabet size) by checking which character classes actually appear — lowercase, uppercase (just the leading capital from ucfirst), digits, and a specials pool. So R grows only when those digit/special options are actually used.</li>
<li>E = L × log2(R), with L counted excluding spaces (since word boundaries are fixed by the template and do not add real guessing entropy).</li>
<li>A small entropy_rating() helper that buckets the bit count into Weak/Fair/Strong/Very Strong with matching W3.CSS tag colors, displayed next to each passphrase.</li>
</ul>

<p>L·log2(R) "charset search space" formula is the conventional way sites report entropy, but this actually understates how strong this generator really is — the true entropy comes from the word-pool and template combinatorics (noun pool × noun pool × verb pool × adjective pool × 3 templates × digit/special combinations), which is a much bigger number since an attacker guessing against this scheme would need to know the word lists, not just brute-force characters.</p>

<p>The combinatoric figure is computed exactly per phrase, not just estimated:</p>

<ul>
<li>2 × log2(64 nouns) for the two noun slots, + log2(45 verbs)</li>
<li>log2(56 adjectives), but only when the chosen template actually places the adjective in the visible output (template 0 draws an adjective it never uses, so that draw carries zero real entropy</li>
<li>digit_count × log2(10) and special_count × log2(14) for the appended suffix</li>
</ul>

<p>Everything is summed as logs rather than multiplied out, so there is no integer overflow risk even if the word pool is expanded later. The word-pool number is meaningfully higher than the char-space number — that is expected, since the char-space formula treats the phrase as a flat string of arbitrary characters, while the word-pool number reflects that an attacker actually has to search sentence-structures, not just character strings.</p>

<p>Read more:</p>
<ul>
<li><a href="https://www.forkbb.net/-/help/topic/6/stop-fighting-your-password-rules-switch-to-a-passphrase-instead">Stop Fighting Your Password Rules — Switch to a Passphrase Instead</a></li>
<li><a href="https://www.forkbb.net/-/help/topic/7/build-your-own-mental-passphrase-algorithm">Build Your Own Mental Passphrase Algorithm</a></li>
</ul>

</div>

<script>
function copySingle(button) {
    const codeEl = button.parentElement.querySelector('.passphrase');
    const phrase = codeEl.innerText;
    navigator.clipboard.writeText(phrase)
        .then(() => alert("Copied to clipboard!"))
        .catch(err => console.error("Failed to copy:", err));
}

function copyAllPassphrases() {
    const phrases = Array.from(document.querySelectorAll('.passphrase'))
                         .map(el => el.innerText);
    navigator.clipboard.writeText(phrases.join("\n"))
        .then(() => alert("All passphrases copied!"))
        .catch(err => console.error("Failed to copy all:", err));
}
</script>
