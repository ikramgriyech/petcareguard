<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pet Profile Editor</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    * {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  background: linear-gradient(to bottom, #f7f7f3, #e9e9e5);
  min-height: 100vh;
  color: #23484f;
}

.container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 1.5rem;
}

/* Header styles */
.header {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(8px);
  margin-bottom: 2rem;
  border-radius: 0.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
}

.logo img {
  width: 96px;
  height: 96px;
  object-fit: contain;
}

.navigation {
  display: flex;
  gap: 2rem;
}

.nav-link {
  color: #23484f;
  text-decoration: none;
  font-size: 1.5rem;
  transition: color 0.3s;
}

.nav-link:hover {
  color: #0fabff;
}

/* Main content styles */
.main-content {
  background: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(8px);
  border-radius: 1.5rem;
  border: 1px solid rgba(0, 0, 0, 0.1);
  padding: 2rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.page-title {
  text-align: center;
  font-size: 2.5rem;
  color: #23484f;
  margin-bottom: 3rem;
}

/* Form styles */
.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 3rem;
}

@media (min-width: 1024px) {
  .form-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.form-column {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.profile-picture {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.picture-upload {
  width: 192px;
  height: 192px;
  border-radius: 50%;
  border: 2px solid #0fabff;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.5);
  cursor: pointer;
  transition: background-color 0.3s;
}

.picture-upload:hover {
  background: rgba(255, 255, 255, 0.8);
}

.camera-icon {
  width: 64px;
  height: 64px;
  transition: transform 0.3s;
}

.picture-upload:hover .camera-icon {
  transform: scale(1.1);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

label {
  font-size: 1.25rem;
  font-weight: 500;
}

input,
select,
textarea {
  height: 48px;
  padding: 0.5rem 1rem;
  border: 1px solid rgba(15, 171, 255, 0.3);
  border-radius: 0.375rem;
  font-size: 1.125rem;
  transition: border-color 0.3s;
}

input:focus,
select:focus,
textarea:focus {
  outline: none;
  border-color: #0fabff;
}

textarea {
  height: 120px;
  resize: vertical;
}

.checkbox-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1.125rem;
}

.date-inputs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

/* Pets section styles */
.pets-section {
  margin-top: 4rem;
}

.pets-section h2 {
  text-align: center;
  font-size: 1.875rem;
  margin-bottom: 2rem;
}

.pet-form {
  background: rgba(246, 246, 241, 0.5);
  border-radius: 1rem;
  border: 1px solid rgba(0, 0, 0, 0.05);
  padding: 2rem;
}

.button-container {
  display: flex;
  justify-content: center;
  margin-top: 2rem;
}

.add-pet-button {
  height: 48px;
  padding: 0 2rem;
  background-color: #0fabff;
  color: white;
  border: none;
  border-radius: 9999px;
  font-size: 1.125rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.3s;
}

.add-pet-button:hover {
  background-color: #0f9fee;
}
  </style>
</head>
<body>
  <div class="container">
    <?php include("../includes/header.php"); ?>

    <!-- Main Content -->
    <main class="main-content">
      <h1 class="page-title">Edit your profile</h1>

      <form class="profile-form">
        <div class="form-grid">
          <!-- Left Column -->
          <div class="form-column">
            <!-- Profile Picture -->
            <div class="profile-picture">
              <div class="picture-upload">
                <img src="https://images.pexels.com/photos/6001815/pexels-photo-6001815.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2" alt="Camera Icon" class="camera-icon">
              </div>
              <h2>Profile Picture</h2>
            </div>

            <!-- Personal Information -->
            <div class="form-section">
              <div class="form-group">
                <label for="fullName">Full Name</label>
                <input type="text" id="fullName" value="Sara El Amrani">
              </div>

              <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" value="sarael3@gmail.com">
              </div>

              <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" value="+212 607920834">
              </div>

              <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" value="Casablanca">
              </div>
            </div>
          </div>

          <!-- Right Column -->
          <div class="form-column">
            <div class="form-group">
              <label for="userType">I am a</label>
              <select id="userType">
                <option value="Both">Both</option>
                <option value="PetOwner">Pet Owner</option>
                <option value="PetSitter">Pet Sitter</option>
              </select>
            </div>

            <div class="form-group">
              <label for="bio">Short Bio</label>
              <textarea id="bio" placeholder="Write something about yourself"></textarea>
            </div>

            <div class="form-group">
              <label>Animals Accepted</label>
              <div class="checkbox-grid">
                <label class="checkbox-label">
                  <input type="checkbox" name="animals" value="dogs">
                  Dogs
                </label>
                <label class="checkbox-label">
                  <input type="checkbox" name="animals" value="cats">
                  Cats
                </label>
                <label class="checkbox-label">
                  <input type="checkbox" name="animals" value="birds">
                  Birds
                </label>
                <label class="checkbox-label">
                  <input type="checkbox" name="animals" value="rabbits">
                  Rabbits
                </label>
              </div>
            </div>

            <div class="form-group">
              <label>Available Dates</label>
              <div class="date-inputs">
                <div class="date-group">
                  <label for="dateFrom">From</label>
                  <input type="date" id="dateFrom">
                </div>
                <div class="date-group">
                  <label for="dateTo">To</label>
                  <input type="date" id="dateTo">
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="price">Price per Day</label>
              <input type="number" id="price" placeholder="Enter amount">
            </div>
          </div>
        </div>

        <!-- Pets Section -->
        <section class="pets-section">
          <h2>YOUR PETS</h2>
          <div class="pet-form">
            <div class="form-grid">
              <div class="form-column">
                <div class="form-group">
                  <label for="petName">Animal Name</label>
                  <input type="text" id="petName">
                </div>

                <div class="form-group">
                  <label for="petType">Type</label>
                  <input type="text" id="petType">
                </div>

                <div class="form-group">
                  <label for="breed">Breed</label>
                  <input type="text" id="breed">
                </div>
              </div>

              <div class="form-column">
                <div class="form-group">
                  <label for="age">Age</label>
                  <input type="text" id="age">
                </div>

                <div class="form-group">
                  <label for="healthNotes">Health Notes</label>
                  <textarea id="healthNotes" placeholder="Enter any health-related information"></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="button-container">
            <button type="button" class="add-pet-button">Add Another Animal</button>
          </div>
        </section>
      </form>
    </main>
  </div>
</body>
</html>