
<?php
session_start();
require_once "db.php";

$error = "";
$success = "";

// LOAD USER PROFILE
$user = null;

if (isset($_SESSION["user_id"])) {
    $userId = (int)$_SESSION["user_id"];

    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE id = ?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        unset($_SESSION["user_id"]);
    }
}

// OPTIONAL MEDICINE DETAILS: save only when a medicine name is provided.
$medicineMessage = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_medicine"]) && isset($_SESSION["user_id"])) {
    $medicineName = trim($_POST["medicine_name"] ?? "");
    $dosage = trim($_POST["dosage"] ?? "");
    $frequency = trim($_POST["frequency"] ?? "");
    if ($medicineName === "") {
        $medicineMessage = "Medicine details skipped; you can continue without them.";
    } else {
        $uid = (int)$_SESSION["user_id"];
        $checkTable = $conn->query("SHOW TABLES LIKE 'user_medicines'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            $medicineMessage = "Medicine storage table is missing. Run the user_medicines SQL setup first.";
        } else {
            $saveMed = $conn->prepare("INSERT INTO user_medicines (user_id, medicine_name, dosage, frequency) VALUES (?, ?, ?, ?)");
            if ($saveMed) {
                $saveMed->bind_param("isss", $uid, $medicineName, $dosage, $frequency);
                $medicineMessage = $saveMed->execute() ? "Medicine details saved." : "Could not save medicine details.";
                $saveMed->close();
            } else {
                $medicineMessage = "Could not prepare medicine save.";
            }
        }
    }
}

// MODULE 2: LOAD FOOD DATABASE
$allFoods = [];
$eligibleFoods = [];
$recommendedFoods = [];
$totalCost = 0;
$totalCalories = 0;
$totalProtein = 0;
$planStatus = "";

if ($user) {
    $result = $conn->query(
        "SELECT * FROM foods ORDER BY price ASC"
    );

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $allFoods[] = $row;
        }
    }

    // MODULE 3: DIET PREFERENCE AND ALLERGY FILTERING
    $userAllergies = array_filter(
        array_map(
            fn($x) => strtolower(trim($x)),
            explode(",", $user["allergies"] ?? "")
        )
    );

    foreach ($allFoods as $food) {
        if (
            $user["diet_preference"] === "Vegetarian" &&
            (int)$food["vegetarian"] !== 1
        ) {
            continue;
        }

        if (
            $user["diet_preference"] === "Vegan" &&
            (int)$food["vegan"] !== 1
        ) {
            continue;
        }

        // Allergy filter: match allergy terms against both the food name
        // and the food's stored allergens list (case-insensitive).
        $foodName = strtolower(trim($food["food_name"] ?? $food["name"] ?? ""));
        $foodAllergenText = strtolower(trim($food["allergens"] ?? ""));
        $hasAllergyMatch = false;

        foreach ($userAllergies as $allergy) {
            $allergy = strtolower(trim($allergy));
            if ($allergy === "") {
                continue;
            }

            if (
                strpos($foodName, $allergy) !== false ||
                strpos($foodAllergenText, $allergy) !== false
            ) {
                $hasAllergyMatch = true;
                break;
            }
        }

        if ($hasAllergyMatch) {
            continue;
        }

        $eligibleFoods[] = $food;
    }

    // MODULE 4: BASIC COST OPTIMIZATION
    // Select the cheapest eligible food for each meal.
    $mealTypes = [
        "Breakfast", "Lunch", "Dinner", "Snack"
    ];

    $candidatePlan = [];

    foreach ($mealTypes as $meal) {
        $bestFood = null;

        foreach ($eligibleFoods as $food) {
            if ($food["meal_type"] !== $meal) {
                continue;
            }

            if (
                $bestFood === null ||
                (float)$food["price"] <
                (float)$bestFood["price"]
            ) {
                $bestFood = $food;
            }
        }

        if ($bestFood !== null) {
            $candidatePlan[] = $bestFood;
        }
    }

    $candidateCost = 0;

    foreach ($candidatePlan as $food) {
        $candidateCost += (float)$food["price"];
    }

    if (count($candidatePlan) < 3) {
        $planStatus =
            "Not enough eligible food options. Check the food database.";
    } elseif ($candidateCost > (float)$user["daily_budget"]) {
        $planStatus =
            "The basic meal plan exceeds your budget. Increase the budget or update verified food prices.";
    } else {
        $recommendedFoods = $candidatePlan;
        $totalCost = $candidateCost;

        foreach ($recommendedFoods as $food) {
            $totalCalories += (float)$food["calories"];
            $totalProtein += (float)$food["protein_g"];
        }

        $planStatus = "Plan generated within your budget.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>AI Diet Cost Optimization</title>

<style>
:root {
    --bg: #080B12;
    --navy: #152443;
    --panel: #101722;
    --panel-raised: #172033;
    --border: #293750;
    --text: #F4F6FB;
    --muted: #A6B0C2;
    --accent: #7896C8;
    --accent-hover: #8DA9D8;
}
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Inter, "Segoe UI", Arial, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.6;
}

header {
    background: linear-gradient(135deg, #101827 0%, #152443 100%);
    color: var(--text);
    padding: 34px 18px 30px;
    text-align: center;
    border-bottom: 1px solid #263754;
}
header h1 {
    margin: 0 0 8px;
    letter-spacing: .2px;
    font-size: clamp(25px, 4vw, 34px);
}
header p { color: var(--muted); margin: 0; }

main {
    max-width: 1000px;
    margin: 30px auto;
    padding: 0 18px;
}

.card {
    background: var(--panel);
    border: 1px solid #202B3D;
    padding: 25px;
    border-radius: 16px;
    margin-bottom: 22px;
    box-shadow: 0 14px 36px rgba(0,0,0,.18);
}

h2 {
    color: #E8EDF7;
    font-size: 21px;
    margin: 0 0 18px;
}
p { color: #D0D7E4; }

label {
    display: block;
    color: #E8EDF7;
    font-weight: 600;
    margin-top: 15px;
}
input, select {
    width: 100%;
    padding: 12px 13px;
    margin-top: 7px;
    border: 1px solid #344158;
    border-radius: 9px;
    background: #0B111C;
    color: var(--text);
    outline: none;
}
input::placeholder { color: #78849A; }
input:focus, select:focus {
    border-color: #7896C8;
    box-shadow: 0 0 0 3px rgba(120,150,200,.15);
}

button, .button {
    display: inline-block;
    background: #263B60;
    color: #FFFFFF;
    border: 1px solid #3A527B;
    padding: 12px 18px;
    border-radius: 9px;
    margin-top: 18px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    transition: background .18s ease, transform .18s ease;
}
button:hover, .button:hover {
    background: #344D76;
    transform: translateY(-1px);
}
.secondary { background: #202735; border-color: #3A4353; }
.secondary:hover { background: #2B3445; }

.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}
.metric {
    background: var(--panel-raised);
    border: 1px solid #293750;
    padding: 17px;
    border-radius: 11px;
    color: var(--muted);
}
.metric strong {
    display: block;
    color: #FFFFFF;
    font-size: 23px;
    margin-top: 8px;
    overflow-wrap: anywhere;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 17px;
    overflow: hidden;
    border-radius: 9px;
}
th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #2A3445;
}
th { background: #192640; color: #F3F6FC; }
td { color: #D6DCE7; }
tbody tr { background: #111925; }
tbody tr:nth-child(even) { background: #151E2C; }

.success { color: #B9D0F6; }
.error { color: #FF9D9D; }

.note {
    background: #151E2C;
    border: 1px solid #293750;
    color: var(--muted);
    padding: 13px;
    border-radius: 9px;
    line-height: 1.5;
}

@media (max-width: 600px) {
    .grid { grid-template-columns: 1fr; }
    .card { padding: 17px; }
    table { font-size: 13px; }
    th, td { padding: 9px 7px; }
    .button, button { width: 100%; text-align: center; }
}

.site-nav{position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px clamp(16px,4vw,52px);background:#080B12;border-bottom:1px solid #293750;}
.brand{font-weight:800;font-size:18px;color:#fff;text-decoration:none;white-space:nowrap}.brand span{color:#C7D2E7}
.nav-links{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.nav-links a{color:#D4D9E4;text-decoration:none;padding:8px 13px;border-radius:8px;font-weight:600}.nav-links a:hover,.nav-links a.active{background:#051650;color:#fff}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
@media(max-width:600px){.site-nav{align-items:flex-start;flex-direction:column}.nav-links{width:100%;gap:4px}.nav-links a{padding:8px 10px}}

</style>
</head>

<body>
<nav class="site-nav">
  <a class="brand" href="index.php">AI <span>Diet Optimizer</span></a>
  <div class="nav-links">
    <a class="active" href="index.php">Home</a>
    <?php if ($user): ?>
      <a href="history.php">History</a>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
    <?php endif; ?>
  </div>
</nav>


<header>
    <h1>AI Diet Cost Optimization</h1>
    <p>Affordable food planning based on your preferences</p>
</header>

<main>

<section class="card">
    <h2>Your Profile</h2>

    <?php if ($error !== ""): ?>
        <p class="error">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <?php if (!$user): ?>
        <h2>Welcome to AI Diet Cost Optimization</h2>
        <p>Create an account to get a budget-friendly meal plan tailored to your diet preference and food allergies.</p>
        <div class="actions">
            <a class="button" href="register.php">Create Account</a>
            <a class="button secondary" href="login.php">Login</a>
        </div>
        <?php else: ?>

        <p class="success">
            Welcome, <strong>
            <?= htmlspecialchars($user["name"]) ?>
            </strong>!
        </p>

        <div class="grid">
            <div class="metric">
                Diet Preference
                <strong>
                    <?= htmlspecialchars($user["diet_preference"]) ?>
                </strong>
            </div>

            <div class="metric">
                Daily Budget
                <strong>
                    ₹<?= number_format((float)$user["daily_budget"], 2) ?>
                </strong>
            </div>
        </div>

        <a class="button secondary" href="logout.php">Logout</a>

       <a class="button" href="history.php">
           Diet Plan History & Report
       </a>

    <?php endif; ?>
</section>

<?php if ($user): ?>

<section class="card">
    <h2>Food Availability</h2>
    <p>Food records available: <?= count($allFoods) ?></p>
    <p>Foods matching current filters: <?= count($eligibleFoods) ?></p>
</section>

<section class="card">
    <h2>Your Diet Preferences</h2>

    <p>
        Recommendations are filtered using your diet preference
        and the allergy terms recorded in your profile.
    </p>

    <p>
        <strong>Diet preference:</strong>
        <?= htmlspecialchars($user["diet_preference"]) ?>
    </p>

    <p>
        <strong>Allergies:</strong>
        <?= htmlspecialchars($user["allergies"] ?: "None entered") ?>
    </p>
</section>

<section class="card">
    <h2>Your Optimized Meal Plan</h2>

    <p><?= htmlspecialchars($planStatus) ?></p>

    <?php if (count($recommendedFoods) > 0): ?>

        <div class="grid">
            <div class="metric">
                Estimated Daily Cost
                <strong>₹<?= number_format($totalCost, 2) ?></strong>
            </div>

            <div class="metric">
                Remaining Budget
                <strong>
                    ₹<?= number_format(
                        max(0, (float)$user["daily_budget"] - $totalCost),
                        2
                    ) ?>
                </strong>
            </div>

            <div class="metric">
                Listed Food Calories
                <strong>
                    <?= number_format($totalCalories, 1) ?> kcal
                </strong>
            </div>

            <div class="metric">
                Listed Food Protein
                <strong>
                    <?= number_format($totalProtein, 1) ?> g
                </strong>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Meal</th>
                    <th>Food</th>
                    <th>Calories</th>
                    <th>Protein</th>
                    <th>Cost</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($recommendedFoods as $food): ?>
                <tr>
                    <td><?= htmlspecialchars($food["meal_type"]) ?></td>
                    <td><?= htmlspecialchars($food["food_name"]) ?></td>
                    <td><?= number_format((float)$food["calories"], 1) ?></td>
                    <td><?= number_format((float)$food["protein_g"], 1) ?> g</td>
                    <td>₹<?= number_format((float)$food["price"], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>



    <?php else: ?>

        <p class="error">
            No feasible plan was generated. Check the food records,
            allergies, dietary preferences and budget.
        </p>

    <?php endif; ?>
</section>

<?php
// Show only the current user's saved medicine entries.
$medicineRows = [];
$medCheck = $conn->prepare(
    "SELECT medicine_name, dosage, frequency, created_at
     FROM user_medicines WHERE user_id = ? ORDER BY created_at DESC"
);
if ($medCheck) {
    $medCheck->bind_param("i", $userId);
    if ($medCheck->execute()) {
        $medResult = $medCheck->get_result();
        while ($medRow = $medResult->fetch_assoc()) {
            $medicineRows[] = $medRow;
        }
    }
    $medCheck->close();
}
?>

<section class="card">
    <h2>Medicine Details (Optional)</h2>
    <p>Enter the medicine you take, or leave this section blank and continue with your diet plan.</p>
    <?php if ($medicineMessage !== ""): ?>
        <p class="success"><?= htmlspecialchars($medicineMessage, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>
    <form method="post" action="index.php">
        <label for="medicine_name">Medicine name (optional)</label>
        <input id="medicine_name" name="medicine_name" type="text" placeholder="Enter medicine name">
        <label for="dosage">Dosage (optional)</label>
        <input id="dosage" name="dosage" type="text" placeholder="e.g. 500 mg">
        <label for="frequency">Frequency (optional)</label>
        <input id="frequency" name="frequency" type="text" placeholder="e.g. as prescribed">
        <button type="submit" name="save_medicine" value="1">Save Medicine Details</button>
    </form>

    <?php if (count($medicineRows) > 0): ?>
        <h3>Saved medicine details</h3>
        <?php foreach ($medicineRows as $med): ?>
            <div class="metric" style="margin-top:12px">
                <strong><?= htmlspecialchars($med["medicine_name"], ENT_QUOTES, "UTF-8") ?></strong>
                <span>Dosage: <?= htmlspecialchars($med["dosage"] ?: "Not entered", ENT_QUOTES, "UTF-8") ?></span><br>
                <span>Frequency: <?= htmlspecialchars($med["frequency"] ?: "Not entered", ENT_QUOTES, "UTF-8") ?></span>
                <p><strong>Side-effect status:</strong> Side effects are possible; a medicine-specific risk cannot be predicted reliably without a verified medicine reference and personal clinical details.</p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No medicine details saved. This is optional.</p>
    <?php endif; ?>
    <div class="note" style="margin-top:14px">
        This version does not clinically predict medicine-specific side effects or food interactions. Do not change or stop prescribed medicine based on this page; ask a doctor or pharmacist about medicine-specific risks.
    </div>
</section>

<?php endif; ?>

</main>
</body>
</html>
