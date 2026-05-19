<?php

namespace App\Models;

use CodeIgniter\Model;

class UserLocationsModel extends Model
{
    protected $table            = 'user_locations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['title', 'coord_geom', 'risk_level', 'distance_km', 'closest_fault_name'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    // protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Tüm mülkleri içindeki POINT verisini okunabilir Enlem/Boylam formatına çevirerek getirir.
     */
    public function getAllUserLocations()
    {
        return $this->select("id, title, risk_level, distance_km, closest_fault_name, ST_X(coord_geom) as lat, ST_Y(coord_geom) as lng, created_at")
            ->orderBy('created_at', 'DESC')->findAll();
    }
}
