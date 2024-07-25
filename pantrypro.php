<?php
// Start Output Buffering to access DOM later
ob_start();
?>

<!DOCTYPE html>
	<head>
		<title>PantryPro</title>
		
		<link href="styles.css" rel="stylesheet">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
		<link href="https://fonts.googleapis.com/css2?family=Hind+Vadodara:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	</head>

	<body>

		<main>
			<h1>Welcome to <span id="brand">PantryPro</span></h1>
			<h2>Enter an ingredient to search for recipes!</h2>
			<form action="pantrypro.php" method="post">
				<input type="text" name="ingredient" required>
				<button type="submit" name="submit">Submit</button>
			</form>
			<div id="meals"></div>
		</main>
		<footer>
			<p>Powered by <a href="https://www.themealdb.com/" target="blank">TheMealDB</a></p>
		</footer>
	</body>
</html>

<?php
// Load Guzzle
require 'vendor/autoload.php';

function getRecipes() {
	// Access buffered HTML
	$html = ob_get_clean();

	// Create new DOM reference
	$dom = new DOMDocument();
	libxml_use_internal_errors(true); // Suppress HTML parsing errors
	$dom->loadHTML($html);
	libxml_clear_errors(); // Clear any errors

	// Access DIV where meals will be displayed
	$mealsDiv = $dom->getElementById('meals');

	// Get ingredient to search via POST request from form
	$ingredient = $_POST['ingredient'];

	// Create new Guzzle client
	$client = new \GuzzleHttp\Client(['verify' => false]);

	// Send get request to API, attaching ingredient to the URL
	$response = $client->request('GET', 'www.themealdb.com/api/json/v1/1/filter.php?i=' . $ingredient);

	// Decode response
	$data = json_decode($response->getBody(), true);

	// Extract meals as they are one level down in the returned data array
	$meals = $data['meals'];

	$numMeals = $dom->createElement('p', count($meals) . ' Meals Found!');
	$numMeals->setAttribute('id', 'numMeals');
	$mealsDiv->appendChild($numMeals);

	// Create a "clear" button & attach to meals div
	$clearButton = $dom->createElement('button', 'Clear');
	$clearButton->setAttribute('id', 'clearMeals');
	$mealsDiv->appendChild($clearButton);

	// Check meals array isn't empty
	if ($meals != null) {
		// Loop through meals array
		foreach ($meals as $meal) {
			// API only returned an ID, now use that ID to get recipe
			$url = 'www.themealdb.com/api/json/v1/1/lookup.php?i=' . $meal['idMeal'];
			$response = $client->request('GET', $url);
			$recipe = json_decode($response->getBody(), true)['meals'][0]['strInstructions'];

			// Create a div to contain each meal
			$mealPreview = $dom->createElement('div');
			$mealPreview->setAttribute('class', 'meal');

			// Create meal title, htmlspecialchars is required to handle ampersands
			$mealTitle = $dom->createElement('h3', htmlspecialchars($meal['strMeal'], ENT_QUOTES));
			$mealTitle->setAttribute('class', 'mealTitle');

			// Create meal image
			$mealImage = $dom->createElement('img');
			$mealImage->setAttribute('src', $meal['strMealThumb']);
			$mealImage->setAttribute('class', 'mealImage');

			// Create button to show and hide recipe
			$viewRecipeButton = $dom->createElement('button', 'View Recipe');
			$viewRecipeButton->setAttribute('class', 'viewRecipe');

			// Create div to contain list of ingredients
			$ingredientList = $dom->createElement('div', 'Ingredients: ');
			$ingredientList->setAttribute('class', 'ingredients');
			// Set the display to none so it is hidden to start
			$ingredientList->setAttribute('style', 'display:none;');

			// The API returns all of the ingredients and measures in sepereate entries
			// Loop through all entries from 1 to 20
			$i = 1;
			while ($i <= 20) {
				// Save ingredient and measure
				$ingredient = json_decode($response->getBody(), true)['meals'][0]['strIngredient' . $i];
				$measure = json_decode($response->getBody(), true)['meals'][0]['strMeasure' . $i];

				// Create DIV to contain combined ingredient and measure
				$ingredientDiv = $dom->createElement('div');

				// API always returns 20 ingredients but depending on the recipe
				// some entries will be empty
				if ($ingredient) {
					// Save ingredient to P tag
					$ingredientStr = $dom->createElement('p', $ingredient . ': ');
					// Display set to inline so measure will display alongside
					$ingredientStr->setAttribute('style', 'display:inline;');
					// Attach to ingredient DIV
					$ingredientDiv->appendChild($ingredientStr);
				}

				// Repeat the above process for the measure
				if ($measure) {
					$measureStr = $dom->createElement('p', $measure);
					$measureStr->setAttribute('style', 'display:inline;');
					$ingredientDiv->appendChild($measureStr);
				}

				// Attach the combined ingredient and measure to the list
				$ingredientList->appendChild($ingredientDiv);
				
				// Increment Loop
				$i++;
			}

			// Create P tag for recipe instructions and hide it to start with
			$mealRecipe = $dom->createElement('p', "Instructions: " . $recipe);
			$mealRecipe->setAttribute('class', 'recipe');
			$mealRecipe->setAttribute('style', 'display:none;');

			// Attach all created elements to the meal preview div
			$mealPreview->appendChild($mealTitle);
			$mealPreview->appendChild($mealImage);
			$mealPreview->appendChild($viewRecipeButton);
			$mealPreview->appendChild($mealRecipe);
			$mealPreview->appendChild($ingredientList);

			// Then attach meal preview to the div containing all meals
			$mealsDiv->appendChild($mealPreview);
		}
	} else {
		// API may not contain any meals for the inputted ingredient
		$mealsDiv->nodeValue = "Unable to find any recipes!";
	}

	// Save the modified HTML back to a string
	$modifiedHtml = $dom->saveHTML();

	// Print modified HTML
	echo $modifiedHtml;
}

// Listen for POST request from 'submit' button
if (isset($_POST['submit'])) {
	// Call getRecipes function
	getRecipes();
}
?>

<script>
	// Get all buttons with viewRecipe class
	const recipeButtons = document.querySelectorAll(".viewRecipe");

	// Create show function and pass event as e
	function show(e) {
		// The DIVs are arranged Button->Ingredients->Recipe for each meal
		// To select ingredients, access clicked button from e.target then call nextElementSibling
		let ingredients = e.target.nextElementSibling;
		// Call nextElementSibling on ingredients to select recipes
		let recipes = ingredients.nextElementSibling;


		// Toggle display of ingredients and recipes, and change button text
		if (ingredients.style.display === 'none'){
			ingredients.style.display = 'inline';
			recipes.style.display = 'inline';
			e.target.innerText = ("Hide Recipe");
		} else {
			ingredients.style.display = 'none';
			recipes.style.display = 'none';
			e.target.innerText = ("View Recipe");
		}
	}

	// Attach show function to viewRecipe buttons
	recipeButtons.forEach(element => {
		element.addEventListener("click", show);
	});

	// Get 'Clear' button
	const clearButton = document.getElementById('clearMeals');

	// Create clearMeals function and pass event as e
	function clearMeals(e) {
		// Get all 'meal' divs
		let meals = document.querySelectorAll('.meal');
		// Get p containing num. of meals found
		let numMeals = document.getElementById('numMeals');

		// Loop through all the 'meal' divs
		meals.forEach(meal => {
			// Remove them from the DOM
			meal.remove();
		});

		// Remove 'numMeals' p & 'clear' buttons from DOM
		numMeals.remove();
		e.target.remove();
	}

	// Attach clear function to 'clearMeals' button
	clearButton.addEventListener('click', clearMeals);
</script>