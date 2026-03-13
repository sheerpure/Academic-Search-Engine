const searchButton = document.getElementById('submit_btn');
			const searchInput = document.getElementById('search');
			searchButton.addEventListener('click', () => {
			const inputValue = searchInput.value;
			alert(inputValue);