<?php

namespace Controllers;

require_once 'schemas/Response.php';
require_once 'schemas/Empresa.php';
require_once 'schemas/Usuario.php';
require_once 'schemas/Proyecto.php';
require_once 'models/Proyectos.php';
require_once 'models/Usuarios.php';
require_once 'models/Empresas.php';
require_once 'exceptions/ParameterIsMissingException.php';
require_once 'exceptions/UnauthorizedRequestException.php';
require_once 'exceptions/BadRequestException.php';
require_once 'exceptions/NotFoundException.php';
require_once 'exceptions/InternalServerErrorException.php';

use BadRequestException;
use CompanyModel;
use Empresa;
use Exception;
use Response;
use Proyecto;
use InternalServerErrorException;
use NotFoundException;
use ParameterIsMissingException;
use ProjectModel;
use UnauthorizedRequestException;
use UserModel;
use Usuario;

class ProjectController
{
  private array $requiredParameters = [
    'tema',
    'id_empresa',
    'id_asesor',
    'objetivos',
    'alcances_limitantes',
    'observaciones',
    'cd',
    'estado',
    'motivo',
    'justificacion',
    'resultados_esperados',
    'fecha_presentacion',
    'creado_por'
  ];

  public function __construct(private $projectService)
  {
  }

  public function getRequiredParameters(): array
  {
    return $this->requiredParameters;
  }

  public function createProject(array $projectData): Response
  {
    foreach ($this->getRequiredParameters() as $param) {
      if (!array_key_exists($param, $projectData) || empty(trim($projectData[$param]))) {
        throw new ParameterIsMissingException(
          'Bad Request: Asegúrese de proporcionar todos los campos necesarios para agregar un proyecto',
          400
        );
      }
    }

    $creado_por = (new UserModel())->getById(new Usuario($projectData['creado_por']));
    $usuario = (new UserModel())->getById(new Usuario($projectData['id_asesor']));
    $empresa = (new CompanyModel)->getById($projectData['id_empresa']);

    if (!($usuario->es_asesor)) throw new BadRequestException(
      'Bad Request: El usuario seleccionado no es un asesor.'
    );

    $project = new Proyecto(
      null,
      $projectData['tema'],
      $empresa,
      $usuario,
      $projectData['objetivos'],
      $projectData['alcances_limitantes'],
      $projectData['observaciones'],
      $projectData['cd'],
      $projectData['estado'],
      $projectData['motivo'],
      $projectData['justificacion'],
      $projectData['resultados_esperados'],
      $projectData['fecha_presentacion'],
      $projectData['doc'],
      $creado_por
    );

    $result = $this->projectService->save($project);

    if ($result instanceof Proyecto) {
      return new Response(true, 200, 'El proyecto se ha agregado exitosamente', [$result]);
    } else {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al agregar el proyecto, por favor intente de nuevo.'
      );
    }
  }

  public function getProjectBySubjectId(?int $id_materia): response
  {

    if (!$id_materia) throw new ParameterIsMissingException(
      'Bad Request: Asegurate de proporcionar un id de materia adecuado para realizar esta acción',
      400
    );

    if (!is_integer($id_materia) && $id_materia < 0) throw new ParameterIsMissingException(
      'Bad Request: El id de la materia debe ser un número entero positivo',
      400
    );

    $projects = $this->projectService->getBySubjectId($id_materia);

    return $projects;
  }
  public function getByProjetById(?int $id_proyecto): response
  {

    if (!$id_proyecto) throw new ParameterIsMissingException(
      'Bad Request: Asegurate de proporcionar un id de materia adecuado para realizar esta acción',
      400
    );

    if (!is_integer($id_proyecto) && $id_proyecto < 0) throw new ParameterIsMissingException(
      'Bad Request: El id de la materia debe ser un número entero positivo',
      400
    );

    $projects = $this->projectService->getById($id_proyecto);

    return $projects;
  }

  public function deleteProject(array $projectData, $usuario): Response
  {
    $projectId = $projectData['id'] ?? null;

    if (!$projectId) {
      throw new BadRequestException(
        'Bad Request: Asegúrese de proporcionar los datos necesarios para la eliminación del proyecto.'
      );
    }

    if ($projectData['creado_por'] !== $usuario->id) {
      throw new UnauthorizedRequestException(
        'Unauthorized Request: Este proyecto no fue registrado por ti, no puedes eliminarlo'
      );
    }

    $result = $this->projectService->delete($projectId);

    if ($result) {
      return new Response(true, 201, 'El proyecto ha sido eliminado correctamente');
    } else {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al eliminar el proyecto, por favor intente de nuevo.'
      );
    }
  }

  public function deleteManyProjects(?array $ids, $usuario): Response
  {
    if (is_null($ids) || !is_array($ids)) {
      throw new BadRequestException(
        'Bad Request: Asegúrese de proporcionar un array de IDs para eliminar los proyectos'
      );
    }
    $current_projects = [];
    $projectModel = new ProjectModel;

    foreach ($ids as $id) {
      $current_projects[] = $projectModel->getById($id);
    }

    foreach ($current_projects as $project) {
      if ($project->creado_por->id !== $usuario->id) {
        throw new BadRequestException(
          'Bad Request: Asegúrese de que todos los IDs proporcionados sean enteros y registrados por ti'
        );
      }
    }

    $result = $this->projectService->deleteMany($ids);

    if (!$result) {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al eliminar los proyectos, por favor intente de nuevo'
      );
    }

    return new Response(true, 204, 'Los proyectos han sido eliminados correctamente');
  }

  public function updateProject(array $projectData, $usuario): Response
  {
    $projectId = $projectData['id'] ?? null;

    if (!$projectId) {
      throw new BadRequestException(
        'Bad Request: Asegúrese de proporcionar los datos necesarios para actualizar el proyecto.'
      );
    }

    $currentProject = (new ProjectModel())->getById($projectId);

    if (!$currentProject) {
      throw new NotFoundException(
        'Not Found: El proyecto no existe.'
      );
    }

    if ($currentProject->creado_por->id !== $usuario->id) {
      throw new UnauthorizedRequestException(
        "Unauthorized Request: El proyecto que intentas actualizar no fue creado por ti, por lo que esta acción no puede ser realizada."
      );
    }

    // Validar y sanitizar el input
    $tema = $projectData['tema'] ?? null;
    $idEmpresa = $projectData['id_empresa'] ?? null;
    $idAsesor = $projectData['id_asesor'] ?? null;
    $objetivos = $projectData['objetivos'] ?? null;
    $alcancesLimitantes = $projectData['alcances_limitantes'] ?? null;
    $observaciones = $projectData['observaciones'] ?? null;
    $cd = $projectData['cd'] ?? null;
    $estado = $projectData['estado'] ?? null;
    $motivo = $projectData['motivo'] ?? null;
    $justificacion = $projectData['justificacion'] ?? null;
    $resultadosEsperados = $projectData['resultados_esperados'] ?? null;
    $fechaPresentacion = $projectData['fecha_presentacion'] ?? null;
    $doc = $projectData['doc'] ?? null;

    $project = new Proyecto(
      $projectId,
      $tema,
      $idEmpresa ? new Empresa($idEmpresa) : null,
      $idAsesor ? new Usuario($idAsesor) : null,
      $objetivos,
      $alcancesLimitantes,
      $observaciones,
      $cd,
      $estado,
      $motivo,
      $justificacion,
      $resultadosEsperados,
      $fechaPresentacion,
      $doc,
      $currentProject->creado_por
    );

    try {
      $result = $this->projectService->update($project);
    } catch (Exception $e) {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al actualizar el proyecto, por favor intente de nuevo',
        500,
        $e
      );
    }

    if ($result instanceof Proyecto) {
      return new Response(true, 201, 'El proyecto ha sido actualizado exitosamente');
    } else {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al actualizar el proyecto, por favor intente de nuevo'
      );
    }
  }

  public function addJudgeToProject(?array $data): Response
  {
    if (!isset($data['id_proyecto']) || !isset($data['id_jurado'])) {
      throw new ParameterIsMissingException(
        'Bad Request: Asegurate de proporcionar los datos necesarios para agregar un jurado a un proyecto',
        400
      );
    }

    $usuario = (new UserModel)->getById(new Usuario($data['id_jurado']));

    if (!($usuario->es_jurado)) throw new BadRequestException(
      'Bad Request: El usuario seleccionado no es un jurado.'
    );

    $result = $this->projectService->addJudgeToProject($data['id_proyecto'], $data['id_jurado']);

    if ($result) {
      return new Response(
        true,
        200,
        'El proyecto se ha agregado exitosamente',
        ['message' => 'El jurado se ha asignado exitosamente']
      );
    } else {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al asignar el jurado, por favor intente de nuevo.'
      );
    }
  }
  public function addStudentToProject(?array $data): Response
  {
    if (!isset($data['id_proyecto']) || !isset($data['id_estudiante'])) {
      throw new ParameterIsMissingException(
        'Bad Request: Asegurate de proporcionar los datos necesarios para agregar un estudiante a un proyecto',
        400
      );
    }

    $result = $this->projectService->addStudentToProject($data['id_proyecto'], $data['id_estudiante']);

    if ($result) {
      return new Response(
        true,
        200,
        'El proyecto se ha agregado exitosamente',
        ['message' => 'El jurado se ha asignado exitosamente']
      );
    } else {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al asignar el jurado, por favor intente de nuevo.'
      );
    }
  }

  public function deleteJudgeFromProject(array $data): Response
  {

    if (!isset($data['id_jurado']) || !isset($data['id_proyecto'])) {
      throw new BadRequestException(
        'Bad Request: Asegúrese de proporcionar los datos necesarios remover el jurado del proyecto.'
      );
    }


    $result = $this->projectService->deleteJudgeFromProject($data['id_jurado'], $data['id_proyecto']);

    if ($result) {
      return new Response(
        true,
        201,
        'El proyecto ha sido eliminado correctamente',
        [['message' => 'El jurado se ha removido correctamente del proyecto']]
      );
    } else {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al eliminar el jurado del proyecto, por favor intente de nuevo.'
      );
    }
  }

  public function deleteStudentFromProject(array $data): Response
  {

    if (!isset($data['id_alumno']) || !isset($data['id_proyecto'])) {
      throw new BadRequestException(
        'Bad Request: Asegúrese de proporcionar los datos necesarios para remover el estudiante del proyecto.'
      );
    }

    $result = $this->projectService->deleteStudentFromProject($data['id_alumno'], $data['id_proyecto']);

    if ($result) {
      return new Response(
        true,
        201,
        'El proyecto ha sido eliminado correctamente',
        [
          ['message' => 'El estudiante se ha eliminado del proyecto correctamente']
        ]
      );
    } else {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al eliminar el estudiante del proyecto, por favor intente de nuevo.'
      );
    }
  }

  public function getAllProjects(): Response
  {
    $result = $this->projectService->getAll();

    if ($result === false) {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al obtener los proyectos, por favor intente de nuevo'
      );
    }

    if (count($result) === 0) {
      throw new NotFoundException(
        'Not Found: No hay registros de proyectos'
      );
    }

    return new Response(true, 200, 'Proyectos obtenidos exitosamente', $result);
  }

  public function getProjectById(?int $projectId): Response
  {
    if (!$projectId) {
      throw new BadRequestException(
        'Bad Request: Asegúrese de proporcionar un ID válido para obtener el proyecto'
      );
    }

    $result = $this->projectService->getById($projectId);

    if ($result instanceof Proyecto) {
      return new Response(true, 200, 'Proyecto obtenido exitosamente', [$result]);
    } else {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al obtener el proyecto, por favor intente de nuevo'
      );
    }
  }

  public function getProjectByTopic(?string $topic): Response
  {
    if (!$topic) {
      throw new BadRequestException(
        'Bad Request: Asegúrese de proporcionar un tema válido para obtener el proyecto'
      );
    }

    $result = $this->projectService->getByTopic($topic);

    if ($result === false) {
      throw new InternalServerErrorException(
        'Internal Server Error: Ha ocurrido un error al obtener el proyecto, por favor intente de nuevo'
      );
    }

    if (count($result) === 0) {
      throw new NotFoundException(
        'Not Found: No hay proyectos con el tema especificado'
      );
    }

    return new Response(true, 200, 'Proyectos obtenidos exitosamente', $result);
  }
}
