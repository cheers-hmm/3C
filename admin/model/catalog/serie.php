<?php
class ModelCatalogSerie extends Model {
	public function addSerie($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "serie SET parent_id = '" . (int)$data['parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW(), date_added = NOW()");

		$serie_id = $this->db->getLastId();

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "serie SET image = '" . $this->db->escape($data['image']) . "' WHERE serie_id = '" . (int)$serie_id . "'");
		}

		foreach ($data['serie_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "serie_description SET serie_id = '" . (int)$serie_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		// MySQL Hierarchical Data Closure Table Pattern
		$level = 0;

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "serie_path` WHERE serie_id = '" . (int)$data['parent_id'] . "' ORDER BY `level` ASC");

		foreach ($query->rows as $result) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "serie_path` SET `serie_id` = '" . (int)$serie_id . "', `path_id` = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");

			$level++;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "serie_path` SET `serie_id` = '" . (int)$serie_id . "', `path_id` = '" . (int)$serie_id . "', `level` = '" . (int)$level . "'");

		if (isset($data['serie_filter'])) {
			foreach ($data['serie_filter'] as $filter_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "serie_filter SET serie_id = '" . (int)$serie_id . "', filter_id = '" . (int)$filter_id . "'");
			}
		}

		if (isset($data['serie_store'])) {
			foreach ($data['serie_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "serie_to_store SET serie_id = '" . (int)$serie_id . "', store_id = '" . (int)$store_id . "'");
			}
		}
		
		if (isset($data['serie_seo_url'])) {
			foreach ($data['serie_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'serie_id=" . (int)$serie_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}
		
		// Set which layout to use with this serie
		if (isset($data['serie_layout'])) {
			foreach ($data['serie_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "serie_to_layout SET serie_id = '" . (int)$serie_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}

		$this->cache->delete('serie');

		return $serie_id;
	}

	public function editSerie($serie_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "serie SET parent_id = '" . (int)$data['parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW() WHERE serie_id = '" . (int)$serie_id . "'");

		if (isset($data['image'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "serie SET image = '" . $this->db->escape($data['image']) . "' WHERE serie_id = '" . (int)$serie_id . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "serie_description WHERE serie_id = '" . (int)$serie_id . "'");

		foreach ($data['serie_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "serie_description SET serie_id = '" . (int)$serie_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "', description = '" . $this->db->escape($value['description']) . "', meta_title = '" . $this->db->escape($value['meta_title']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		// MySQL Hierarchical Data Closure Table Pattern
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "serie_path` WHERE path_id = '" . (int)$serie_id . "' ORDER BY level ASC");

		if ($query->rows) {
			foreach ($query->rows as $serie_path) {
				// Delete the path below the current one
				$this->db->query("DELETE FROM `" . DB_PREFIX . "serie_path` WHERE serie_id = '" . (int)$serie_path['serie_id'] . "' AND level < '" . (int)$serie_path['level'] . "'");

				$path = array();

				// Get the nodes new parents
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "serie_path` WHERE serie_id = '" . (int)$data['parent_id'] . "' ORDER BY level ASC");

				foreach ($query->rows as $result) {
					$path[] = $result['path_id'];
				}

				// Get whats left of the nodes current path
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "serie_path` WHERE serie_id = '" . (int)$serie_path['serie_id'] . "' ORDER BY level ASC");

				foreach ($query->rows as $result) {
					$path[] = $result['path_id'];
				}

				// Combine the paths with a new level
				$level = 0;

				foreach ($path as $path_id) {
					$this->db->query("REPLACE INTO `" . DB_PREFIX . "serie_path` SET serie_id = '" . (int)$serie_path['serie_id'] . "', `path_id` = '" . (int)$path_id . "', level = '" . (int)$level . "'");

					$level++;
				}
			}
		} else {
			// Delete the path below the current one
			$this->db->query("DELETE FROM `" . DB_PREFIX . "serie_path` WHERE serie_id = '" . (int)$serie_id . "'");

			// Fix for records with no paths
			$level = 0;

			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "serie_path` WHERE serie_id = '" . (int)$data['parent_id'] . "' ORDER BY level ASC");

			foreach ($query->rows as $result) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "serie_path` SET serie_id = '" . (int)$serie_id . "', `path_id` = '" . (int)$result['path_id'] . "', level = '" . (int)$level . "'");

				$level++;
			}

			$this->db->query("REPLACE INTO `" . DB_PREFIX . "serie_path` SET serie_id = '" . (int)$serie_id . "', `path_id` = '" . (int)$serie_id . "', level = '" . (int)$level . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "serie_filter WHERE serie_id = '" . (int)$serie_id . "'");

		if (isset($data['serie_filter'])) {
			foreach ($data['serie_filter'] as $filter_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "serie_filter SET serie_id = '" . (int)$serie_id . "', filter_id = '" . (int)$filter_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "serie_to_store WHERE serie_id = '" . (int)$serie_id . "'");

		if (isset($data['serie_store'])) {
			foreach ($data['serie_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "serie_to_store SET serie_id = '" . (int)$serie_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		// SEO URL
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'serie_id=" . (int)$serie_id . "'");

		if (isset($data['serie_seo_url'])) {
			foreach ($data['serie_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'serie_id=" . (int)$serie_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "serie_to_layout WHERE serie_id = '" . (int)$serie_id . "'");

		if (isset($data['serie_layout'])) {
			foreach ($data['serie_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "serie_to_layout SET serie_id = '" . (int)$serie_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout_id . "'");
			}
		}

		$this->cache->delete('serie');
	}

	public function deleteSerie($serie_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "serie_path WHERE serie_id = '" . (int)$serie_id . "'");

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "serie_path WHERE path_id = '" . (int)$serie_id . "'");

		foreach ($query->rows as $result) {
			$this->deleteSerie($result['serie_id']);
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "serie WHERE serie_id = '" . (int)$serie_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "serie_description WHERE serie_id = '" . (int)$serie_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "serie_filter WHERE serie_id = '" . (int)$serie_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "serie_to_store WHERE serie_id = '" . (int)$serie_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "serie_to_layout WHERE serie_id = '" . (int)$serie_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_serie WHERE serie_id = '" . (int)$serie_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'serie_id=" . (int)$serie_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "coupon_serie WHERE serie_id = '" . (int)$serie_id . "'");

		$this->cache->delete('serie');
	}

	public function repairSeries($parent_id = 0) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "serie WHERE parent_id = '" . (int)$parent_id . "'");

		foreach ($query->rows as $serie) {
			// Delete the path below the current one
			$this->db->query("DELETE FROM `" . DB_PREFIX . "serie_path` WHERE serie_id = '" . (int)$serie['serie_id'] . "'");

			// Fix for records with no paths
			$level = 0;

			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "serie_path` WHERE serie_id = '" . (int)$parent_id . "' ORDER BY level ASC");

			foreach ($query->rows as $result) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "serie_path` SET serie_id = '" . (int)$serie['serie_id'] . "', `path_id` = '" . (int)$result['path_id'] . "', level = '" . (int)$level . "'");

				$level++;
			}

			$this->db->query("REPLACE INTO `" . DB_PREFIX . "serie_path` SET serie_id = '" . (int)$serie['serie_id'] . "', `path_id` = '" . (int)$serie['serie_id'] . "', level = '" . (int)$level . "'");

			$this->repairSeries($serie['serie_id']);
		}
	}

	public function getSerie($serie_id) {
		$query = $this->db->query("SELECT DISTINCT *, (SELECT GROUP_CONCAT(cd1.name ORDER BY level SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') FROM " . DB_PREFIX . "serie_path cp LEFT JOIN " . DB_PREFIX . "serie_description cd1 ON (cp.path_id = cd1.serie_id AND cp.serie_id != cp.path_id) WHERE cp.serie_id = c.serie_id AND cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY cp.serie_id) AS path FROM " . DB_PREFIX . "serie c LEFT JOIN " . DB_PREFIX . "serie_description cd2 ON (c.serie_id = cd2.serie_id) WHERE c.serie_id = '" . (int)$serie_id . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'");
		
		return $query->row;
	}

	public function getSeries($data = array()) {
		$sql = "SELECT cp.serie_id AS serie_id, GROUP_CONCAT(cd1.name ORDER BY cp.level SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') AS name, c1.parent_id, c1.sort_order FROM " . DB_PREFIX . "serie_path cp LEFT JOIN " . DB_PREFIX . "serie c1 ON (cp.serie_id = c1.serie_id) LEFT JOIN " . DB_PREFIX . "serie c2 ON (cp.path_id = c2.serie_id) LEFT JOIN " . DB_PREFIX . "serie_description cd1 ON (cp.path_id = cd1.serie_id) LEFT JOIN " . DB_PREFIX . "serie_description cd2 ON (cp.serie_id = cd2.serie_id) WHERE cd1.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd2.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_name'])) {
			$sql .= " AND cd2.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		$sql .= " GROUP BY cp.serie_id";

		$sort_data = array(
			'name',
			'sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY name, sort_order";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getSerieDescriptions($serie_id) {
		$serie_description_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "serie_description WHERE serie_id = '" . (int)$serie_id . "'");

		foreach ($query->rows as $result) {
			$serie_description_data[$result['language_id']] = array(
				'name'             => $result['name'],
				'meta_title'       => $result['meta_title'],
				'meta_description' => $result['meta_description'],
				'meta_keyword'     => $result['meta_keyword'],
				'description'      => $result['description']
			);
		}

		return $serie_description_data;
	}
	
	public function getSeriePath($serie_id) {
		$query = $this->db->query("SELECT serie_id, path_id, level FROM " . DB_PREFIX . "serie_path WHERE serie_id = '" . (int)$serie_id . "'");

		return $query->rows;
	}
	
	public function getSerieFilters($serie_id) {
		$serie_filter_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "serie_filter WHERE serie_id = '" . (int)$serie_id . "'");

		foreach ($query->rows as $result) {
			$serie_filter_data[] = $result['filter_id'];
		}

		return $serie_filter_data;
	}

	public function getSerieStores($serie_id) {
		$serie_store_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "serie_to_store WHERE serie_id = '" . (int)$serie_id . "'");

		foreach ($query->rows as $result) {
			$serie_store_data[] = $result['store_id'];
		}

		return $serie_store_data;
	}
	
	public function getSerieSeoUrls($serie_id) {
		$serie_seo_url_data = array();
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query = 'serie_id=" . (int)$serie_id . "'");

		foreach ($query->rows as $result) {
			$serie_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $serie_seo_url_data;
	}
	
	public function getSerieLayouts($serie_id) {
		$serie_layout_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "serie_to_layout WHERE serie_id = '" . (int)$serie_id . "'");

		foreach ($query->rows as $result) {
			$serie_layout_data[$result['store_id']] = $result['layout_id'];
		}

		return $serie_layout_data;
	}

	public function getTotalSeries() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "serie");

		return $query->row['total'];
	}
	
	public function getTotalSeriesByLayoutId($layout_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "serie_to_layout WHERE layout_id = '" . (int)$layout_id . "'");

		return $query->row['total'];
	}	
}
