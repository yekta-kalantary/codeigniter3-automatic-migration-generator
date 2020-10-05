#Create a Migration File from current DB



When all goes well it will create a file in migrations directory like 20201005113401_create_blog.php


#To use:

1: Enable migrations and set version to 0;

2: Set migration type as timestamp;

3: Enable to write migration folder;

3: Copy AliRK_Migration.php to your library folder (/application/library);

4: In controller:


	public function generate($table = FALSE) {
		$this->load->library('AliRK_Migration');
		if ($table) {
			$this->alirk_migration->generate($table);
		}
		else {
			$this->alirk_migration->generate();
		}
	}
    
