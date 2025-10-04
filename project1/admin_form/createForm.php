<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Create Tool</title>
    <meta charset="utf-8" />
    <link href="/home/aw399/public_html/data311-project1-webapp/style.css"
          type="text/css" rel="stylesheet" />
</head>
<body>
    <h1>Admin Tools</h1>
    <form action="createForm.php" method="post">
        <fieldset>
            <label for="entityType">Create:</label>
            <select id="entityType" name="entityType" required>
                <option value="">Select...</option>
                <option value="location">Location</option>
                <option value="waste">Waste</option>
                <option value="auditor">Auditor</option>
            </select>
        </fieldset>

        <!-- Location Fields -->
        <div id="locationFields" class="entity-section">
            <fieldset>
                <legend>Location</legend>
                <p>
                    <label for="locName">Location Name</label>
                    <input type="text" id="locName" name="locName" placeholder="Name of Buliding" required />
                </p>
                <p>
                    <label for="floorNum">Floor Number</label>
                    <input type="number" id="floorNum" name="floorNum" placeholder="Floor # (optional)" />
                </p>
                <!-- Potentially based on values that already exist from locations entered, since they are the only ones -->
                <p>
                    <label for="locType">Location Type</label>
                    <input type="text" id="locType" name="locType" placeholder="Type of Location" required />
                </p>
            </fieldset>
        </div>

        <!-- Waste Fields -->
        <div id="wasteFields" class="entity-section">
            <fieldset>
                <legend>Waste</legend>
                <p>
                    <label for="wasteType">Waste Type</label>
                    <input type="text" id="wasteType" name="wasteType" required />
                </p>
                <!-- Make sure this is dynamic drop down based on waste types that exist in database -->
                <p>
                    <label for="wasteParent">Parent Waste</label>
                    <input type="text" id="wasteParent" name="wasteParent" required />
                </p>
            </fieldset>
        </div>

        <!-- Auditor Fields -->
        <div id="auditorFields" class="entity-section">
            <fieldset>
                <legend>Auditor</legend>
                <!-- Example fields, replace with your actual schema -->
                <p>
                    <label for="auditorfName">First Name</label>
                    <input type="text" id="auditorfName" name="auditorfName" required />
                </p>
                <p>
                    <label for="auditorlName">Last Name</label>
                    <input type="text" id="auditorlName" name="auditorlName" required />
                </p>
                <!-- Affilitations can be hard coded into a drop down -->
                <p>
                    <label for="auditorAffil">Affiliation</label>
                    <input type="text" id="auditorAffil" name="auditorAffil" required />
                </p>
            </fieldset>
        </div>

        <input type="hidden" name="crud" value="create" />
        <fieldset>
            <legend>Submit Changes</legend>
            <input type="submit" />
        </fieldset>
    </form>

    <footer>
        <br>
        <button>
            <a href="index.php" style="text-decoration: none; color: black;">Home Page</a>
        </button>
    </footer>

    <script>
        // not sure what to do here
    </script>
</body>
</html>
